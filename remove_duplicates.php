<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Rate;

// Find and remove duplicate rates
// Duplicates are: same carrier_id, weight_slab, shipment_type, country_code, but different country_name values
echo "Analyzing for duplicate rates...\n\n";

$duplicates = Rate::selectRaw('carrier_id, weight_slab, shipment_type, country_code, COUNT(*) as count')
    ->whereNotNull('country_code')
    ->groupBy('carrier_id', 'weight_slab', 'shipment_type', 'country_code')
    ->having('count', '>', 1)
    ->get();

echo "Found " . $duplicates->count() . " sets of duplicate rates\n\n";

$ratesToDelete = 0;
$examinedCount = 0;

foreach ($duplicates as $dup) {
    $rates = Rate::where('carrier_id', $dup->carrier_id)
        ->where('weight_slab', $dup->weight_slab)
        ->where('shipment_type', $dup->shipment_type)
        ->where('country_code', $dup->country_code)
        ->get();
    
    if ($rates->count() > 1) {
        // Keep the one with country_name, delete others
        $withCountryName = $rates->where('country_name', '!=', null)->first();
        
        foreach ($rates as $rate) {
            if ($withCountryName && $rate->id !== $withCountryName->id) {
                $rate->delete();
                $ratesToDelete++;
            }
        }
        $examinedCount++;
    }
}

echo "Deleted $ratesToDelete duplicate rate entries\n";

// Verify
$stillDuplicate = Rate::selectRaw('carrier_id, weight_slab, shipment_type, country_code, COUNT(*) as count')
    ->whereNotNull('country_code')
    ->groupBy('carrier_id', 'weight_slab', 'shipment_type', 'country_code')
    ->having('count', '>', 1)
    ->count();

echo "\nRemaining duplicates: $stillDuplicate\n";

// Check total rates
$totalRates = Rate::count();
echo "Total rates remaining: $totalRates\n";
