<?php

namespace App\Services;

use App\Models\Carrier;
use App\Models\CountryZone;
use App\Models\Rate;
use Illuminate\Support\Collection;

class QuoteService
{
    protected const EXCHANGE_RATE = 122.73;

    protected const ABOVE_30_BANDS = [
        [
            'from' => 30.1,
            'to' => 70.0,
            'rates' => [1 => 1050.0, 2 => 1150.0, 3 => 1520.0, 4 => 1180.0, 5 => 1800.0, 6 => 1990.0, 7 => 2580.0],
        ],
        [
            'from' => 70.1,
            'to' => 300.0,
            'rates' => [1 => 1050.0, 2 => 1150.0, 3 => 1520.0, 4 => 1180.0, 5 => 1800.0, 6 => 1990.0, 7 => 2580.0],
        ],
    ];

    /**
     * Calculate shipping quotes for a given country and weight.
     *
     * @param string $countryCode ISO 2-letter country code
     * @param float $weight Weight in KG
     * @return array
     */
    public function getQuotes(string $countryCode, float $weight, string $shipmentType = 'non_document'): array
    {
        $normalizedWeight = $this->normalizeWeight($weight);

        $carriers = Carrier::with('rates')->get();
        $options = collect();

        foreach ($carriers as $carrier) {
            $carrierZone = $this->resolveZoneForCarrier($carrier, $countryCode);
            $price = $this->calculateCarrierPrice($carrier, $countryCode, $carrierZone, $normalizedWeight, $shipmentType);

            if ($price !== null && $price > 0) {
                $basePrice = $price;
                // Fuel surcharge is already included in the configured rate list.
                $fuelSurcharge = 0.0;

                // Profit margin is also included in the configured rate list.
                $margin = 0.0;
                $finalPrice = $basePrice;

                // Store Result (Convert to BDT if needed)
                $finalPriceBDT = ($carrier->currency === 'USD')
                    ? $finalPrice * self::EXCHANGE_RATE
                    : $finalPrice;

                $options->push([
                    'carrier' => $carrier->name,
                    'shipment_type' => $shipmentType,
                    'zone' => $carrierZone,
                    'weight' => $normalizedWeight,
                    'base_price' => round($basePrice, 2),
                    'fuel_surcharge' => round($fuelSurcharge, 2),
                    'margin' => round($margin, 2),
                    'total_price' => round($finalPrice, 2),
                    'total_price_bdt' => round($finalPriceBDT, 2),
                    'currency' => $carrier->currency,
                ]);
            }
        }

        $sortedOptions = $options->sortBy('total_price_bdt')->values();

        return [
            'cheapest' => $sortedOptions->first(),
            'options' => $sortedOptions->toArray(),
        ];
    }

    /**
     * Price each product independently and sum totals per carrier.
     *
     * @param array<int, array{country:string,type:string,weight:float}> $products
     */
    public function getQuotesForProducts(array $products): array
    {
        if (empty($products)) {
            return ['cheapest' => null, 'options' => []];
        }

        $carriers = Carrier::with('rates')->get();
        $options = collect();

        foreach ($carriers as $carrier) {
            $perProduct = [];
            $totalBasePrice = 0.0;
            $totalFinalPrice = 0.0;

            foreach ($products as $index => $product) {
                $countryCode = strtoupper($product['country']);
                $shipmentType = $product['type'];
                $normalizedWeight = $this->normalizeWeight((float) $product['weight']);

                $carrierZone = $this->resolveZoneForCarrier($carrier, $countryCode);

                $price = $this->calculateCarrierPrice($carrier, $countryCode, $carrierZone, $normalizedWeight, $shipmentType);

                // Skip this carrier if any one product is not serviceable.
                if ($price === null || $price <= 0) {
                    continue 2;
                }

                $perProduct[] = [
                    'product_no' => $index + 1,
                    'country' => $countryCode,
                    'shipment_type' => $shipmentType,
                    'weight' => $normalizedWeight,
                    'price' => round($price, 2),
                ];

                $totalBasePrice += $price;
                $totalFinalPrice += $price;
            }

            $totalFinalPriceBDT = ($carrier->currency === 'USD')
                ? $totalFinalPrice * self::EXCHANGE_RATE
                : $totalFinalPrice;

            $options->push([
                'carrier' => $carrier->name,
                'base_price' => round($totalBasePrice, 2),
                'fuel_surcharge' => 0.0,
                'margin' => 0.0,
                'total_price' => round($totalFinalPrice, 2),
                'total_price_bdt' => round($totalFinalPriceBDT, 2),
                'currency' => $carrier->currency,
                'per_product' => $perProduct,
            ]);
        }

        $sortedOptions = $options->sortBy('total_price_bdt')->values();

        return [
            'cheapest' => $sortedOptions->first(),
            'options' => $sortedOptions->toArray(),
            'summary' => [
                'product_count' => count($products),
                'total_input_weight' => round(array_sum(array_map(fn ($p) => (float) $p['weight'], $products)), 2),
            ],
        ];
    }

    /**
     * Normalize weight to the nearest 0.5kg.
     */
    protected function normalizeWeight(float $weight): float
    {
        return ceil($weight * 2) / 2;
    }

    /**
     * Resolve country zone for a specific carrier.
     */
    protected function resolveZoneForCarrier(Carrier $carrier, string $countryCode): ?int
    {
        $code = strtoupper($countryCode);
        if ($carrier->name === 'DHL-Bangladesh') {
            $carrierZone = CountryZone::where('country_code', $code)
                ->where('carrier_id', $carrier->id)
                ->value('zone');

            if ($carrierZone !== null) {
                return (int) $carrierZone;
            }

            return null;
        }

        return CountryZone::where('country_code', $code)
            ->whereNull('carrier_id')
            ->value('zone');
    }

    /**
     * Calculate base price for a specific carrier.
     */
    protected function calculateCarrierPrice(Carrier $carrier, string $countryCode, ?int $zone, float $weight, string $shipmentType): ?float
    {
        $effectiveShipmentType = $shipmentType;

        // Business rule: for DHL-Bangladesh only, document shipments above 2kg
        // are priced using non-document rates.
        if (
            $carrier->name === 'DHL-Bangladesh'
            && $shipmentType === 'document'
            && $weight > 2.0
        ) {
            $effectiveShipmentType = 'non_document';
        }

        // 1. Try Zone-based Pricing (DHL-Bangladesh)
        if ($zone !== null) {
            if ($effectiveShipmentType === 'non_document' && $weight > 30.0) {
                $above30Rate = $this->getAbove30PerKgRate($carrier, $zone, $weight);

                if ($above30Rate !== null) {
                    return $weight * $above30Rate;
                }

                // No zone-band match for this carrier; continue to country-based lookup.
            }

            $rate = Rate::where('carrier_id', $carrier->id)
                ->where('shipment_type', $effectiveShipmentType)
                ->where('zone', $zone)
                ->where('weight_slab', '<=', $weight)
                ->orderBy('weight_slab', 'desc')
                ->first();

            if ($rate) {
                // Handle Heavy Shipment (> 30kg)
                if ($weight > 30.0 && $rate->per_kg_rate !== null) {
                    return $weight * $rate->per_kg_rate;
                }

                // Find smallest slab >= weight
                $exactSlab = Rate::where('carrier_id', $carrier->id)
                    ->where('shipment_type', $effectiveShipmentType)
                    ->where('zone', $zone)
                    ->where('weight_slab', '>=', $weight)
                    ->orderBy('weight_slab', 'asc')
                    ->first();

                if ($exactSlab) {
                    return $exactSlab->price;
                }
            }
        }

        // 2. Try Country-based Pricing (Master Air style)
        // For document shipments with weight > 1kg, use increment calculation
        if ($effectiveShipmentType === 'document' && $weight > 1.0) {
            $docPrice = $this->calculateDocumentWeightPrice($carrier, $countryCode, $weight);
            if ($docPrice !== null) {
                return $docPrice;
            }
        }

        // Standard weight slab lookup (also works for country_code or country_name)
        $countryRate = Rate::where('carrier_id', $carrier->id)
            ->where('shipment_type', $effectiveShipmentType)
            ->where('weight_slab', '<=', $weight)
            ->where(function ($query) use ($countryCode) {
                $query->where('country_code', $countryCode)
                    ->orWhere('country_name', $countryCode); // Also try country_name
            })
            ->orderBy('weight_slab', 'desc')
            ->first();

        if ($countryRate) {
            // Handle Per-KG logic for Master Air (e.g. 21kg+, 31kg+)
            if ($countryRate->per_kg_rate !== null) {
                // Special handling for per_0_5_kg rates (10-21kg range)
                if ($countryRate->rate_type === 'per_0_5_kg' && $weight > 10.0) {
                    $baseRate = Rate::where('carrier_id', $carrier->id)
                        ->where('shipment_type', $effectiveShipmentType)
                        ->where('weight_slab', 10.0)
                        ->where(function ($query) use ($countryCode) {
                            $query->where('country_code', $countryCode)
                                ->orWhere('country_name', $countryCode);
                        })
                        ->first();

                    if ($baseRate) {
                        $extraWeight = $weight - 10.0;
                        $increments = ceil($extraWeight / 0.5);
                        return $baseRate->price + ($increments * $countryRate->per_kg_rate);
                    }
                }
                // Standard per-kg calculation
                return $weight * $countryRate->per_kg_rate;
            }

            // Find smallest slab >= weight
            $exactCountrySlab = Rate::where('carrier_id', $carrier->id)
                ->where('shipment_type', $effectiveShipmentType)
                ->where('weight_slab', '>=', $weight)
                ->where(function ($query) use ($countryCode) {
                    $query->where('country_code', $countryCode)
                        ->orWhere('country_name', $countryCode);
                })
                ->orderBy('weight_slab', 'asc')
                ->first();

            if ($exactCountrySlab) {
                return $exactCountrySlab->price;
            }
        }

        return null; // No rate found
    }

    /**
     * Calculate document weight with increments (for weights > 1kg).
     * Documents stored as: 0.5kg, 1.0kg, 1.5kg, 2.0kg, ...
     * Or: 0.5kg base, 1.0kg base, and increment values in 1.5kg, 2.0kg, etc.
     */
    protected function calculateDocumentWeightPrice(Carrier $carrier, string $countryCode, float $weight): ?float
    {
        // Get the rate entry for the exact weight or a calculated based on increments
        $exactRate = Rate::where('carrier_id', $carrier->id)
            ->where('shipment_type', 'document')
            ->where('weight_slab', $weight)
            ->where(function ($query) use ($countryCode) {
                $query->where('country_code', $countryCode)
                    ->orWhere('country_name', $countryCode);
            })
            ->first();

        if ($exactRate) {
            return $exactRate->price;
        }

        // If no exact weight found, try to calculate from base 1kg + increments
        $baseRate = Rate::where('carrier_id', $carrier->id)
            ->where('shipment_type', 'document')
            ->where('weight_slab', 1.0)
            ->where(function ($query) use ($countryCode) {
                $query->where('country_code', $countryCode)
                    ->orWhere('country_name', $countryCode);
            })
            ->first();

        if (!$baseRate) {
            return null;
        }

        $basePrice =  $baseRate->price;
        $extraWeight = $weight - 1.0;
        $increments = ceil($extraWeight / 0.5);

        // Try to find the increment rate from the next slab entry
        $nextSlab = Rate::where('carrier_id', $carrier->id)
            ->where('shipment_type', 'document')
            ->where('weight_slab', '>', 1.0)
            ->where(function ($query) use ($countryCode) {
                $query->where('country_code', $countryCode)
                    ->orWhere('country_name', $countryCode);
            })
            ->orderBy('weight_slab', 'asc')
            ->first();

        if ($nextSlab && $nextSlab->weight_slab === 1.5) {
            // Increment rate = (1.5kg price - 1kg price) / 1
            $incrementRate = $nextSlab->price - $basePrice;
            return $basePrice + ($increments * $incrementRate);
        }

        return $basePrice; // Fallback to base rate
    }

    protected function getAbove30PerKgRate(Carrier $carrier, int $zone, float $weight): ?float
    {
        // Prefer DB-defined per-kg thresholds (imported from DHL sheet),
        // then fall back to legacy constants for backward compatibility.
        $dbRate = Rate::where('carrier_id', $carrier->id)
            ->where('shipment_type', 'non_document')
            ->where('zone', $zone)
            ->whereNotNull('per_kg_rate')
            ->where('weight_slab', '<=', $weight)
            ->orderBy('weight_slab', 'desc')
            ->first();

        if ($dbRate && $dbRate->per_kg_rate !== null) {
            return (float) $dbRate->per_kg_rate;
        }

        // Legacy hardcoded bands are specific to DHL-Bangladesh only.
        if ($carrier->name === 'DHL-Bangladesh') {
            foreach (self::ABOVE_30_BANDS as $band) {
                if ($weight >= $band['from'] && $weight <= $band['to']) {
                    return $band['rates'][$zone] ?? null;
                }
            }
        }

        return null;
    }
}
