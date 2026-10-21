<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\Quote;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class QuotelineStoreTest extends TestCase
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
            'name' => 'DAF XF 106 Sideskirt set',
            'oem' => '2119711',
            'brand' => 'DAF',
            'condition' => 'used',
            'quantity' => 5,
            'price' => 795,
            'image' => '/img/placeholder-part.png',
            'category' => 'Body',
            'colours' => [],
            ...$attributes,
        ]);
    }

    private function addLine(array $body, ?Quote $quote = null, ?Workshop $workshop = null): TestResponse
    {
        $quote ??= $this->quote;
        $workshop ??= $this->workshop;

        return $this->withToken($workshop->token)->postJson("/api/quote/{$quote->id}/quoteline", $body);
    }

    public function test_adds_a_line_and_returns_the_quote_with_totals(): void
    {
        $part = $this->part(['price' => 100, 'quantity' => 10]);

        $this->addLine(['partId' => $part->id, 'quantity' => 3])
            ->assertOk()
            ->assertJsonCount(1, 'lines')
            ->assertJsonPath('lines.0.partId', $part->id)
            ->assertJsonPath('lines.0.quantity', 3)
            ->assertJsonPath('lines.0.backorder', false)
            ->assertJsonPath('warnings', [])
            // 300.00 - 10% = 270.00, + 21% VAT = 56.70
            ->assertJsonPath('totals.subtotal', '300.00')
            ->assertJsonPath('totals.discount', '30.00')
            ->assertJsonPath('totals.vat', '56.70')
            ->assertJsonPath('totals.total', '326.70');
    }

    public function test_adding_the_same_part_and_colour_merges_into_one_line(): void
    {
        $part = $this->part(['colours' => ['white', 'red'], 'quantity' => 10]);

        $this->addLine(['partId' => $part->id, 'quantity' => 2, 'colour' => 'red'])->assertOk();
        $this->addLine(['partId' => $part->id, 'quantity' => 3, 'colour' => 'red'])
            ->assertOk()
            ->assertJsonCount(1, 'lines')
            ->assertJsonPath('lines.0.quantity', 5);
    }

    public function test_a_different_colour_is_a_separate_line(): void
    {
        $part = $this->part(['colours' => ['white', 'red'], 'quantity' => 10]);

        $this->addLine(['partId' => $part->id, 'quantity' => 1, 'colour' => 'red'])->assertOk();
        $this->addLine(['partId' => $part->id, 'quantity' => 1, 'colour' => 'white'])
            ->assertOk()
            ->assertJsonCount(2, 'lines');
    }

    public function test_out_of_stock_part_is_flagged_as_backorder_with_a_warning(): void
    {
        $part = $this->part(['quantity' => 0]);

        $this->addLine(['partId' => $part->id, 'quantity' => 50])
            ->assertOk()
            ->assertJsonPath('lines.0.backorder', true)
            ->assertJsonCount(1, 'warnings');
    }

    public function test_quantity_above_stock_is_rejected(): void
    {
        $part = $this->part(['quantity' => 2]);

        $this->addLine(['partId' => $part->id, 'quantity' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_merged_quantity_above_stock_is_rejected_and_the_line_is_unchanged(): void
    {
        $part = $this->part(['quantity' => 4]);

        $this->addLine(['partId' => $part->id, 'quantity' => 3])->assertOk();
        $this->addLine(['partId' => $part->id, 'quantity' => 2])->assertJsonValidationErrors('quantity');

        $this->assertSame(3, $this->quote->lines()->sole()->quantity);
    }

    public function test_invalid_quantity_is_rejected(): void
    {
        $part = $this->part();

        $this->addLine(['partId' => $part->id, 'quantity' => 0])->assertJsonValidationErrors('quantity');
        $this->addLine(['partId' => $part->id, 'quantity' => 1.5])->assertJsonValidationErrors('quantity');
        $this->addLine(['partId' => $part->id])->assertJsonValidationErrors('quantity');
    }

    public function test_unknown_part_is_rejected(): void
    {
        $this->addLine(['partId' => 999, 'quantity' => 1])->assertJsonValidationErrors('partId');
    }

    public function test_colour_must_be_one_of_the_parts_colours(): void
    {
        $part = $this->part(['colours' => ['white', 'red']]);

        $this->addLine(['partId' => $part->id, 'quantity' => 1, 'colour' => 'green'])
            ->assertJsonValidationErrors('colour');
        $this->addLine(['partId' => $part->id, 'quantity' => 1])
            ->assertJsonValidationErrors('colour');
    }

    public function test_colour_must_be_empty_for_a_part_without_colours(): void
    {
        $part = $this->part(['colours' => []]);

        $this->addLine(['partId' => $part->id, 'quantity' => 1, 'colour' => 'red'])
            ->assertJsonValidationErrors('colour');
    }

    public function test_submitted_quote_returns_409(): void
    {
        $this->quote->update(['status' => 'submitted']);
        $part = $this->part();

        $this->addLine(['partId' => $part->id, 'quantity' => 1])->assertStatus(409);
        $this->assertSame(0, $this->quote->lines()->count());
    }

    public function test_another_workshops_quote_returns_404(): void
    {
        $other = Workshop::factory()->create();
        $part = $this->part();

        $this->addLine(['partId' => $part->id, 'quantity' => 1], workshop: $other)->assertNotFound();
        $this->assertSame(0, $this->quote->lines()->count());
    }

    public function test_missing_token_returns_401(): void
    {
        $this->postJson("/api/quote/{$this->quote->id}/quoteline", [])->assertUnauthorized();
    }

    public function test_workshop_token_is_not_exposed(): void
    {
        $part = $this->part();

        $response = $this->addLine(['partId' => $part->id, 'quantity' => 1]);

        $this->assertStringNotContainsString($this->workshop->token, $response->getContent());
    }
}
