<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Rate;
use App\Models\CountryZone;

// Common country name to code mapping (for countries in the Excel file)
$countryMapping = [
    'Australia' => 'AU',
    'Brunei' => 'BN',
    'China' => 'CN',
    'Cambodia' => 'KH',
    'Indonesia' => 'ID',
    'Japan' => 'JP',
    'Macau' => 'MO',
    'Malaysia' => 'MY',
    'Myanmar' => 'MM',
    'Newzeland' => 'NZ', // Note: typo in Excel
    'New Zealand' => 'NZ',
    'Pakistan' => 'PK',
    'Philippines' => 'PH',
    'Singapore' => 'SG',
    'South Korea' => 'KR',
    'Thailand' => 'TH',
    'Taiwan' => 'TW',
    'Vietnam' => 'VN',
    'Bahrain' => 'BH',
    'Egypt' => 'EG',
    'India' => 'IN',
    'Iran' => 'IR',
    'Iraq' => 'IQ',
    'Israel' => 'IL',
    'Jordan' => 'JO',
    'Kuwait' => 'KW',
    'Lebanon' => 'LB',
    'Oman' => 'OM',
    'Qatar' => 'QA',
    'Saudi Arabia' => 'SA',
    'U.A.E' => 'AE',
    'UAE' => 'AE',
    'Yemen' => 'YE',
    'Austria' => 'AT',
    'Belgium' => 'BE',
    'Bulgaria' => 'BG',
    'Bosnia' => 'BA',
    'Czech Republic' => 'CZ',
    'Cyprus' => 'CY',
    'Croatia' => 'HR',
    'Denmark' => 'DK',
    'Estonia' => 'EE',
    'Finland' => 'FI',
    'France' => 'FR',
    'Germany' => 'DE',
    'Greece' => 'GR',
    'Hungary' => 'HU',
    'Ireland' => 'IE',
    'Iceland' => 'IS',
    'Italy' => 'IT',
    'Latvia' => 'LV',
    'Lithuania' => 'LT',
    'Luxembourg' => 'LU',
    'Malta' => 'MT',
    'Netherlands' => 'NL',
    'Norway' => 'NO',
    'Poland' => 'PL',
    'Portugal' => 'PT',
    'Romania' => 'RO',
    'Russia' => 'RU',
    'Slovakia' => 'SK',
    'Slovenia' => 'SI',
    'Spain' => 'ES',
    'Sweden' => 'SE',
    'Switzerland' => 'CH',
    'Taiwan' => 'TW',
    'Turkey' => 'TR',
    'U.K' => 'GB',
    'UK' => 'GB',
    'Ukraine' => 'UA',
    'Canada' => 'CA',
    'United States' => 'US',
    'U.S.A' => 'US',
    'USA' => 'US',
    'Mexico' => 'MX',
    'Brazil' => 'BR',
    'Argentina' => 'AR',
    'Chile' => 'CL',
    'Colombia' => 'CO',
    'Ecuador' => 'EC',
    'Peru' => 'PE',
    'Australia' => 'AU',
    'New Zealand' => 'NZ',
    'South Africa' => 'ZA',
];

// Get all unique country names from rates with country_name set
$countryNames = Rate::whereNotNull('country_name')
    ->distinct('country_name')
    ->pluck('country_name')
    ->toArray();

echo "Found " . count($countryNames) . " unique country names in rates table\n";
echo "Updating rates with country codes...\n\n";

$updated = 0;
$notFound = [];

foreach ($countryNames as $name) {
    $code = $countryMapping[$name] ?? null;
    
    if ($code) {
        $count = Rate::where('country_name', $name)->update(['country_code' => $code]);
        $updated += $count;
        echo "  {$name} => {$code} ({$count} rates)\n";
    } else {
        $notFound[] = $name;
    }
}

if (!empty($notFound)) {
    echo "\nCountries not found in mapping:\n";
    foreach ($notFound as $name) {
        echo "  - {$name}\n";
    }
}

echo "\nTotal rates updated with country codes: {$updated}\n";

// Verify
$ratesWithBothCodes = Rate::whereNotNull('country_code')
    ->whereNotNull('country_name')
    ->count();

echo "Rates with both country_code and country_name: {$ratesWithBothCodes}\n";
