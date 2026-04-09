<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Rate;
use App\Models\Carrier;

// Get Singapore-DHL carrier
$carrier = Carrier::where('name', 'Singapore-DHL')->first();

if (!$carrier) {
    echo "Singapore-DHL not found!\n";
    echo "Available carriers:\n";
    foreach (Carrier::all() as $c) {
        echo "  - {$c->name}\n";
    }
    exit;
}

echo "Singapore-DHL Carrier ID: {$carrier->id}\n\n";

// Check rates for Australia
echo "=== DOCUMENT RATES FOR AUSTRALIA (AU) ===\n";
$docRates = Rate::where('carrier_id', $carrier->id)
    ->where('country_code', 'AU')
    ->where('shipment_type', 'document')
    ->orderBy('weight_slab')
    ->get();

foreach ($docRates as $rate) {
    echo "  {$rate->weight_slab}kg: \${$rate->price} (country_code={$rate->country_code}, country_name={$rate->country_name})\n";
}

echo "\n=== NON-DOCUMENT RATES FOR AUSTRALIA (AU) ===\n";
$nonDocRates = Rate::where('carrier_id', $carrier->id)
    ->where('country_code', 'AU')
    ->where('shipment_type', 'non_document')
    ->orderBy('weight_slab')
    ->get();

foreach ($nonDocRates as $rate) {
    if ($rate->weight_slab <= 10 || $rate->weight_slab >= 10.5) {
        echo "  {$rate->weight_slab}kg: \${$rate->price}" . ($rate->per_kg_rate ? " (per_kg={$rate->per_kg_rate}, type={$rate->rate_type})" : "") . "\n";
    }
}

echo "\n=== ALL DOCUMENT 0.5KG RATES FOR TEST1 ===\n";
$all0_5 = Rate::where('shipment_type', 'document')
    ->where('weight_slab', 0.5)
    ->orderBy('price')
    ->take(5)
    ->get();

foreach ($all0_5 as $rate) {
    $c = Carrier::find($rate->carrier_id);
    echo "  {$c->name}: \${$rate->price}\n";
}
