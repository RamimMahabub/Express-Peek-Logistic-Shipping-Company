<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Rate;
use App\Models\Carrier;

$carrier = Carrier::where('name', 'Singapore-DHL')->first();

if ($carrier) {
    echo "Singapore-DHL Carrier ID: " . $carrier->id . "\n";
    echo "\nDocument rates for Australia:\n";
    $docRates = Rate::where('carrier_id', $carrier->id)
        ->where('country_name', 'Australia')
        ->where('shipment_type', 'document')
        ->orderBy('weight_slab')
        ->get();
    
    foreach ($docRates as $rate) {
        echo "  {$rate->weight_slab}kg: \${$rate->price}\n";
    }

    echo "\nNon-document rates for Australia (0.5-10kg):\n";
    $nonDocRates = Rate::where('carrier_id', $carrier->id)
        ->where('country_name', 'Australia')
        ->where('shipment_type', 'non_document')
        ->where('weight_slab', '<=', 10)
        ->orderBy('weight_slab')
        ->get();
    
    foreach ($nonDocRates as $rate) {
        echo "  {$rate->weight_slab}kg: \${$rate->price}\n";
    }

    echo "\nNon-document per-kg rates for Australia:\n";
    $perKgRates = Rate::where('carrier_id', $carrier->id)
        ->where('country_name', 'Australia')
        ->where('shipment_type', 'non_document')
        ->where('per_kg_rate', '!=', null)
        ->orderBy('weight_slab')
        ->get();
    
    foreach ($perKgRates as $rate) {
        echo "  {$rate->weight_slab}kg+: \${$rate->per_kg_rate}/kg (" . ($rate->rate_type ?? 'per_kg') . ")\n";
    }
} else {
    echo "Singapore-DHL carrier not found\n";
    echo "\nAvailable carriers:\n";
    Carrier::all()->each(function($c) {
        echo "  - {$c->name}\n";
    });
}
