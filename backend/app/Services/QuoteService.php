<?php

namespace App\Services;

use App\Exceptions\QuoteNotEditableException;
use App\Models\Part;
use App\Models\Quote;
use App\Models\Quoteline;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quote business rules, independent of the HTTP layer.
 */
class QuoteService
{
    public const VAT_PERCENTAGE = 21;

    /**
     * Add a part to a draft quote, merging into an existing line with the same colour
     */
    public function addLine(Quote $quote, Part $part, int $quantity, ?string $colour): Quoteline
    {
        $colour = $colour === '' ? null : $colour;

        return DB::transaction(function () use ($quote, $part, $quantity, $colour) {
            $quote = $this->lockDraft($quote);

            $this->assertValidColour($part, $colour);

            $line = $quote->lines()
                ->where('part_id', $part->id)
                ->where('colour', $colour)
                ->first() ?? new Quoteline(['part_id' => $part->id, 'colour' => $colour, 'quantity' => 0]);

            $line->quantity += $quantity;
            $this->assertValidQuantity($part, $line->quantity);
            $line->backorder = !$this->inStock($part);

            $quote->lines()->save($line);

            return $line;
        });
    }

    /**
     * Change a line's quantity and/or colour. Switching to a colour that is already
     * on the quote merges both lines, so the merge rule holds after edits too.
     *
     */
    public function updateLine(Quoteline $line, int $quantity, ?string $colour): Quoteline
    {
        $colour = $colour === '' ? null : $colour;

        return DB::transaction(function () use ($line, $quantity, $colour) {
            $quote = $this->lockDraft($line->quote);
            $part = $line->part;

            $this->assertValidColour($part, $colour);

            $other = $quote->lines()
                ->where('part_id', $part->id)
                ->where('colour', $colour)
                ->whereKeyNot($line->id)
                ->first();

            if ($other) {
                $other->quantity += $quantity;
                $this->assertValidQuantity($part, $other->quantity);
                $other->backorder = !$this->inStock($part);
                $other->save();
                $line->delete();

                return $other;
            }

            $this->assertValidQuantity($part, $quantity);
            $line->quantity = $quantity;
            $line->colour = $colour;
            $line->backorder = !$this->inStock($part);
            $line->save();

            return $line;
        });
    }

    public function removeLine(Quoteline $line): void
    {
        DB::transaction(function () use ($line) {
            $this->lockDraft($line->quote);
            $line->delete();
        });
    }

    /**
     * Lock the quote: re-check stock, snapshot unit prices and store the totals.
     *
     * @throws QuoteNotEditableException when the quote is already submitted
     * @throws ValidationException when the quote is empty or a line exceeds the current stock
     */
    public function submit(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote) {
            $quote = $this->lockDraft($quote);
            $quote->load('lines.part', 'workshop');

            if ($quote->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'A quote needs at least one line before it can be submitted.',
                ]);
            }

            // Stock may have changed since the lines were added, backorder lines are exempt
            $errors = $quote->lines
                ->reject(fn (Quoteline $line) => $line->backorder || $line->quantity <= $line->part->quantity)
                ->mapWithKeys(fn (Quoteline $line) => [
                    "lines.{$line->id}.quantity" => "{$line->part->name}: only {$line->part->quantity} in stock, the quote contains {$line->quantity}.",
                ]);

            if ($errors->isNotEmpty()) {
                throw ValidationException::withMessages($errors->all());
            }

            $totals = $this->totals($quote);

            foreach ($quote->lines as $line) {
                $line->price = $line->part->price;
                $line->save();
            }

            $quote->totals = $totals;
            $quote->status = 'submitted';
            $quote->submitted_at = now();
            $quote->save();

            return $quote;
        });
    }

    /**
     * Live totals for a draft, the stored snapshot for a submitted quote.
     *
     * @return array{subtotal: string, discount: string, vat: string, total: string, discountPercentage: int}
     */
    public function totals(Quote $quote): array
    {
        if ($quote->status === 'submitted' && $quote->totals) {
            return $quote->totals;
        }

        $quote->loadMissing('lines.part', 'workshop');
        $discountPercentage = (int) $quote->workshop->discount;

        // Work in cents and round at each step, as the business rules require
        $subtotal = $quote->lines->sum(fn (Quoteline $line) => $this->unitPrice($line, $quote) * $line->quantity);
        $discount = (int) round($subtotal * $discountPercentage / 100);
        $vat = (int) round(($subtotal - $discount) * self::VAT_PERCENTAGE / 100);
        $total = $subtotal - $discount + $vat;

        return [
            'subtotal' =>$subtotal,
            'discount' => $discount,
            'vat' => $vat,
            'total' => $total,
            'discountPercentage' => $discountPercentage,
        ];
    }

    /**
     * Human readable warnings, one per backorder line.
     *
     * @return list<array{lineId: int, message: string}>
     */
    public function warnings(Quote $quote): array
    {
        $quote->loadMissing('lines.part');

        return $quote->lines
            ->where('backorder', true)
            ->map(fn (Quoteline $line) => [
                'lineId' => $line->id,
                'message' => "{$line->part->name} is out of stock and will be placed on backorder.",
            ])
            ->values()
            ->all();
    }

    /**
     * The quote as every quote endpoint returns it: lines, prices, totals and warnings.
     */
    public function present(Quote $quote): array
    {
        $quote->load('lines.part', 'workshop');

        return [
            'id' => $quote->id,
            'status' => $quote->status,
            'submittedAt' => $quote->submitted_at?->toIso8601String(),
            'lines' => $quote->lines->map(fn (Quoteline $line) => [
                'id' => $line->id,
                'partId' => $line->part_id,
                'part' => [
                    'id' => $line->part->id,
                    'name' => $line->part->name,
                    'oem' => $line->part->oem,
                    'brand' => $line->part->brand,
                    'colours' => $line->part->colours ?? [],
                    'stockQuantity' => $line->part->quantity,
                ],
                'quantity' => $line->quantity,
                'colour' => $line->colour,
                'backorder' => $line->backorder,
                'unitPrice' => $this->unitPrice($line, $quote),
                'lineTotal' => $this->unitPrice($line, $quote) * $line->quantity,
            ])->values()->all(),
            'totals' => $this->totals($quote),
            'warnings' => $this->warnings($quote),
        ];
    }

    private function lockDraft(Quote $quote): Quote
    {
        // Lock the quote so concurrent edits can't race each other or a submit
        $quote = Quote::lockForUpdate()->findOrFail($quote->id);

        if ($quote->status !== 'draft') {

        }

        return $quote;
    }

    /**
     * The snapshot after submit, the current part price while draft.
     */
    private function unitPrice(Quoteline $line, Quote $quote): string|float|int
    {
        return $quote->status === 'submitted' ? $line->price : $line->part->price;
    }

    private function inStock(Part $part): bool
    {
        return $part->quantity > 0;
    }

    private function assertValidColour(Part $part, ?string $colour): void
    {
        $colours = $part->colours ?? [];

        if ($colours === [] && $colour !== null) {
            throw ValidationException::withMessages([
                'colour' => 'This part is not available in colours.',
            ]);
        }

        if ($colours !== [] && !in_array($colour, $colours, true)) {
            throw ValidationException::withMessages([
                'colour' => 'The colour must be one of: ' . implode(', ', $colours) . '.',
            ]);
        }
    }

    private function assertValidQuantity(Part $part, int $quantity): void
    {
        // Out of stock parts can be ordered in any quantity, on backorder
        if ($this->inStock($part) && $quantity > $part->quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$part->quantity} in stock, the quote would contain {$quantity}.",
            ]);
        }
    }
}
