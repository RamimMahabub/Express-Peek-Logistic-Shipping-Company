<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CountryZone;
use App\Models\Rate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuoteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic data for testing
        $zone1 = CountryZone::create(['country_code' => 'IN', 'country_name' => 'India', 'zone' => 1]);
        $zone4 = CountryZone::create(['country_code' => 'GB', 'country_name' => 'United Kingdom', 'zone' => 4]);

        $dhl = Carrier::create([
            'name' => 'DHL-Bangladesh',
            'fuel_surcharge_percent' => 39.00,
            'profit_margin_percent' => 10.00,
            'currency' => 'BDT',
        ]);

        // Zone 1 rates
        Rate::create(['carrier_id' => $dhl->id, 'zone' => 1, 'shipment_type' => 'non_document', 'weight_slab' => 0.5, 'price' => 3640]);
        Rate::create(['carrier_id' => $dhl->id, 'zone' => 1, 'shipment_type' => 'non_document', 'weight_slab' => 1.0, 'price' => 4780]);
        Rate::create(['carrier_id' => $dhl->id, 'zone' => 1, 'shipment_type' => 'non_document', 'weight_slab' => 2.5, 'price' => 9000]);
        Rate::create(['carrier_id' => $dhl->id, 'zone' => 1, 'shipment_type' => 'non_document', 'weight_slab' => 30.0, 'price' => 39760, 'per_kg_rate' => 1050]);

        // Country specific rate (Master Air style)
        $sDhl = Carrier::create([
            'name' => 'S-DHL',
            'fuel_surcharge_percent' => 15.00,
            'profit_margin_percent' => 15.00,
            'currency' => 'USD',
        ]);

        Rate::create([
            'carrier_id' => $sDhl->id,
            'country_code' => 'AU',
            'shipment_type' => 'non_document',
            'weight_slab' => 21.0,
            'price' => 7.76,
            'per_kg_rate' => 7.76,
        ]);
    }

    #[Test]
    public function it_returns_quotes_for_zone_based_country()
    {
        $response = $this->postJson('/api/quote', [
            'country' => 'IN',
            'type' => 'non_document',
            'weight' => 0.8
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cheapest.carrier', 'DHL-Bangladesh');
        
        $this->assertEquals(1, $response->json('data.cheapest.weight'));
    }

    #[Test]
    public function it_handles_heavy_shipment_rules()
    {
        $response = $this->postJson('/api/quote', [
            'country' => 'IN',
            'type' => 'non_document',
            'weight' => 32.0
        ]);

        $response->assertStatus(200);
        $this->assertEquals(33600, $response->json('data.cheapest.base_price'));
    }

    #[Test]
    public function it_returns_error_for_invalid_input()
    {
        $response = $this->postJson('/api/quote', [
            'country' => 'INDIA',
            'type' => 'invalid_type',
            'weight' => 'abc'
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function dhl_bangladesh_document_above_2kg_is_priced_as_non_document()
    {
        $response = $this->postJson('/api/quote', [
            'products' => [
                [
                    'country' => 'IN',
                    'type' => 'document',
                    'weight' => 2.5,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cheapest.carrier', 'DHL-Bangladesh');

        // Fallback expected to non-document 2.5kg slab for DHL-Bangladesh
        $this->assertEquals(9000, $response->json('data.cheapest.base_price'));
        $this->assertEquals(9000, $response->json('data.cheapest.total_price'));
    }

    #[Test]
    public function it_sums_prices_for_multiple_product_rows()
    {
        $response = $this->postJson('/api/quote', [
            'products' => [
                [
                    'country' => 'IN',
                    'type' => 'non_document',
                    'weight' => 0.5,
                ],
                [
                    'country' => 'IN',
                    'type' => 'non_document',
                    'weight' => 1.0,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cheapest.carrier', 'DHL-Bangladesh');

        // Product-1 (0.5kg) => 3640, Product-2 (1.0kg) => 4780, summed => 8420
        $this->assertEquals(8420, $response->json('data.cheapest.total_price'));
        $this->assertCount(2, $response->json('data.cheapest.per_product'));
    }

    #[Test]
    public function non_dhl_carrier_uses_country_per_kg_rate_above_30kg_not_dhl_zone_band()
    {
        // Add zone mapping for GB to reproduce the real condition where zone exists.
        CountryZone::updateOrCreate(
            ['country_code' => 'GB'],
            ['country_name' => 'United Kingdom', 'zone' => 4]
        );

        $sDhl = Carrier::where('name', 'S-DHL')->firstOrFail();

        Rate::create([
            'carrier_id' => $sDhl->id,
            'country_code' => 'GB',
            'shipment_type' => 'non_document',
            'weight_slab' => 31.0,
            'price' => 10.00,
            'per_kg_rate' => 10.00,
        ]);

        $response = $this->postJson('/api/quote', [
            'country' => 'GB',
            'type' => 'non_document',
            'weight' => 31.0,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $options = collect($response->json('data.options'));
        $sDhlOption = $options->firstWhere('carrier', 'S-DHL');

        $this->assertNotNull($sDhlOption);
        // Must use country per-kg rule (31 * 10), not DHL zone 4 fallback (31 * 1180 = 36580).
        $this->assertEquals(310.0, (float) $sDhlOption['total_price']);
    }
}
