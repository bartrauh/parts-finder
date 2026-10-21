<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\Quote;
use App\Models\Quoteline;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Workshop $workshop;
    private Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workshop = Workshop::factory()->create(['discount' => 10]);
        $this->quote = $this->workshop->quotes()->create(['status' => 'draft']);
    }

    private function part(array $attributes = []): Part
    {
        return Part::forceCreate([
            'name' => 'Scania Brake disc front',
            'oem' => '1885366',
            'brand' => 'Scania',
            'condition' => 'new',
            'quantity' => 10,
            'price' => 100,
            'image' => '/img/placeholder-part.png',
            'category' => 'Brakes',
            'colours' => [],
            ...$attributes,
        ]);
    }

    private function line(Part $part, int $quantity = 1, ?string $colour = null, bool $backorder = false): Quoteline
    {
        $line = new Quoteline(['part_id' => $part->id, 'quantity' => $quantity, 'colour' => $colour]);
        $line->backorder = $backorder;
        $this->quote->lines()->save($line);

        return $line;
    }

    private function api(?Workshop $workshop = null): static
    {
        return $this->withToken(($workshop ?? $this->workshop)->token);
    }

    public function test_creates_an_empty_draft_with_zero_totals(): void
    {
        $this->api()->postJson('/api/quote')
            ->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('lines', [])
            ->assertJsonPath('totals.subtotal', '0.00')
            ->assertJsonPath('totals.total', '0.00');
    }

    public function test_shows_a_quote_with_lines_and_totals(): void
    {
        $this->line($this->part(['price' => 19.99]), 3);

        // 59.97 - 10% (6.00) = 53.97, VAT 21% = 11.33, total 65.30
        $this->api()->getJson("/api/quote/{$this->quote->id}")
            ->assertOk()
            ->assertJsonPath('lines.0.unitPrice', '19.99')
            ->assertJsonPath('lines.0.lineTotal', '59.97')
            ->assertJsonPath('totals.subtotal', '59.97')
            ->assertJsonPath('totals.discount', '6.00')
            ->assertJsonPath('totals.vat', '11.33')
            ->assertJsonPath('totals.total', '65.30');
    }

    public function test_another_workshops_quote_returns_404(): void
    {
        $other = Workshop::factory()->create();
        $line = $this->line($this->part());

        $this->api($other)->getJson("/api/quote/{$this->quote->id}")->assertNotFound();
        $this->api($other)->patchJson("/api/quote/{$this->quote->id}/quoteline/{$line->id}", ['quantity' => 2])->assertNotFound();
        $this->api($other)->deleteJson("/api/quote/{$this->quote->id}/quoteline/{$line->id}")->assertNotFound();
        $this->api($other)->postJson("/api/quote/{$this->quote->id}/submit")->assertNotFound();
    }

    public function test_a_line_from_another_quote_returns_404(): void
    {
        $otherQuote = $this->workshop->quotes()->create(['status' => 'draft']);
        $line = $this->line($this->part());

        $this->api()->patchJson("/api/quote/{$otherQuote->id}/quoteline/{$line->id}", ['quantity' => 2])->assertNotFound();
        $this->api()->deleteJson("/api/quote/{$otherQuote->id}/quoteline/{$line->id}")->assertNotFound();
    }

    public function test_updates_a_line_quantity(): void
    {
        $line = $this->line($this->part(['price' => 100]), 1);

        $this->api()->patchJson("/api/quote/{$this->quote->id}/quoteline/{$line->id}", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('lines.0.quantity', 4)
            ->assertJsonPath('totals.subtotal', '400.00');
    }

    public function test_update_rejects_quantity_above_stock_and_below_one(): void
    {
        $line = $this->line($this->part(['quantity' => 3]), 1);
        $url = "/api/quote/{$this->quote->id}/quoteline/{$line->id}";

        $this->api()->patchJson($url, ['quantity' => 4])->assertJsonValidationErrors('quantity');
        $this->api()->patchJson($url, ['quantity' => 0])->assertJsonValidationErrors('quantity');
        $this->assertSame(1, $line->fresh()->quantity);
    }

    public function test_changing_colour_to_one_already_on_the_quote_merges_the_lines(): void
    {
        $part = $this->part(['colours' => ['white', 'red']]);
        $red = $this->line($part, 2, 'red');
        $this->line($part, 3, 'white');

        $this->api()->patchJson("/api/quote/{$this->quote->id}/quoteline/{$red->id}", ['colour' => 'white'])
            ->assertOk()
            ->assertJsonCount(1, 'lines')
            ->assertJsonPath('lines.0.colour', 'white')
            ->assertJsonPath('lines.0.quantity', 5);
    }

    public function test_removes_a_line(): void
    {
        $line = $this->line($this->part());

        $this->api()->deleteJson("/api/quote/{$this->quote->id}/quoteline/{$line->id}")
            ->assertOk()
            ->assertJsonPath('lines', [])
            ->assertJsonPath('totals.total', '0.00');
    }

    public function test_submitting_an_empty_quote_is_rejected(): void
    {
        $this->api()->postJson("/api/quote/{$this->quote->id}/submit")
            ->assertJsonValidationErrors('lines');

        $this->assertSame('draft', $this->quote->fresh()->status);
    }

    public function test_submit_rechecks_stock_and_keeps_the_quote_draft_on_failure(): void
    {
        $part = $this->part(['quantity' => 5]);
        $line = $this->line($part, 4);
        $part->forceFill(['quantity' => 2])->save();

        $this->api()->postJson("/api/quote/{$this->quote->id}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors("lines.{$line->id}.quantity");

        $this->assertSame('draft', $this->quote->fresh()->status);
    }

    public function test_backorder_lines_are_exempt_from_the_stock_check(): void
    {
        $this->line($this->part(['quantity' => 0]), 25, backorder: true);

        $this->api()->postJson("/api/quote/{$this->quote->id}/submit")
            ->assertOk()
            ->assertJsonPath('status', 'submitted');
    }

    public function test_submit_locks_the_quote_and_snapshots_prices(): void
    {
        $part = $this->part(['price' => 100]);
        $this->line($part, 2);

        $this->api()->postJson("/api/quote/{$this->quote->id}/submit")
            ->assertOk()
            ->assertJsonPath('status', 'submitted')
            ->assertJsonPath('totals.total', '217.80');

        $this->assertNotNull($this->quote->fresh()->submitted_at);

        // A later price change doesn't touch the submitted quote
        $part->forceFill(['price' => 250])->save();

        $this->api()->getJson("/api/quote/{$this->quote->id}")
            ->assertJsonPath('lines.0.unitPrice', '100.00')
            ->assertJsonPath('lines.0.lineTotal', '200.00')
            ->assertJsonPath('totals.subtotal', '200.00')
            ->assertJsonPath('totals.total', '217.80');
    }

    public function test_every_mutation_on_a_submitted_quote_returns_409(): void
    {
        $part = $this->part();
        $line = $this->line($part, 1);
        $this->quote->update(['status' => 'submitted']);
        $base = "/api/quote/{$this->quote->id}";

        $this->api()->postJson("{$base}/quoteline", ['partId' => $part->id, 'quantity' => 1])->assertStatus(409);
        $this->api()->patchJson("{$base}/quoteline/{$line->id}", ['quantity' => 2])->assertStatus(409);
        $this->api()->deleteJson("{$base}/quoteline/{$line->id}")->assertStatus(409);
        $this->api()->postJson("{$base}/submit")->assertStatus(409);

        $this->assertSame(1, $line->fresh()->quantity);
    }
}
