<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Rate;

// Additional country mappings for typos and variations
$additionalMapping = [
    'GREECE' => 'GR',
    'Maldives' => 'MV',
    'Monaco' => 'MC',
    'Nepal' => 'NP',
    'Phillipines' => 'PH',
    'Philippines' => 'PH',
    'Serbia' => 'RS',
    'Azerbaijan' => 'AZ',
    'Cameroon' => 'CM',
    'Ethiopia' => 'ET',
    'Kenya' => 'KE',
    'Mauritius' => 'MU',
    'Morocco' => 'MA',
    'Mozambique' => 'MZ',
    'Nigeria' => 'NG',
    'Srilanka' => 'LK',
    'Sri Lanka' => 'LK',
    'Tanzania' => 'TZ',
    'Tunisia' => 'TN',
    'Uganda' => 'UG',
    'Uzbekistan' => 'UZ',
    'Zambia' => 'ZM',
    'Zimbabwe' => 'ZW',
    'Uruguay' => 'UY',
    'Hong Kong' => 'HK',
    'Korea' => 'KR',
    'South Korea' => 'KR',
    'Bhutan' => 'BT',
    'Seychelles' => 'SC',
    'UAE Others' => 'AE', // Variations of AE
    'Lebanon' => 'LB',
];

echo "Adding remaining country mappings...\n";
$updated = 0;

foreach ($additionalMapping as $name => $code) {
    $count = Rate::where('country_name', $name)
        ->whereNull('country_code')
        ->update(['country_code' => $code]);
    if ($count > 0) {
        $updated += $count;
        echo "  {$name} => {$code} ({$count} rates)\n";
    }
}

echo "\nUpdated {$updated} more rates\n";

// Special cases to ignore (these don't represent actual destinations)
$namesToIgnore = [
    'DHL Risk fee apply',
    'Iraq-Risk Fee Apply',
    'Libya-Risk Fee Apply',
    'Rest of the world',
    'Somalia-Risk Fee App',
    'Sudan-Risk Fee Appl',
    'Syria - Risk Fee Apply',
    'Yemen-Risk Fee Apply',
    'Lebanon -Risk Fee',
    'UPS Extra $4.6 Apply',
    'USA -N.Y/ N.J/CA',
    'Dubai,Sharjah',
];

echo "\nRates without country codes (special cases - can be ignored):\n";
$remaining = Rate::whereNull('country_code')
    ->distinct('country_name')
    ->orderBy('country_name')
    ->pluck('country_name');

foreach ($remaining as $name) {
    $count = Rate::where('country_name', $name)->count();
    $isSpecial = in_array($name, $namesToIgnore);
    echo "  " . ($isSpecial ? "[IGNORE] " : "") . "{$name} ({$count} rates)\n";
}
