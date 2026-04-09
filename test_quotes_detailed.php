<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\QuoteService;
use App\Models\Carrier;

$quoteService = $app->make(QuoteService::class);

echo "=== QUOTE SERVICE DETAILED TESTS ===\n\n";

// Test 1: Document 0.5kg Australia
echo "Test 1: Document 0.5kg Australia\n";
$quotes = $quoteService->getQuotes('AU', 0.5, 'document');
echo "  Total options: " . count($quotes['options']) . "\n";
foreach ($quotes['options'] as $option) {
    echo "  " . $option['carrier'] . ": " . $option['total_price'] . " " . $option['currency'] . "\n";
}
echo "\n";

// Test 2: Document 1.5kg Australia (should be 27.14 + 9.66 = 36.80 for each carrier with that rate)
echo "Test 2: Document 1.5kg Australia\n";
echo "  Expected for Singapore-DHL: 27.14 + 9.66 = 36.80\n";
echo "  Expected for DUBAI-DHL: 33.93 + 9.2 = 43.13\n";
$quotes = $quoteService->getQuotes('AU', 1.5, 'document');
foreach ($quotes['options'] as $option) {
    if (strpos($option['carrier'], 'DHL') !== false) {
        echo "  " . $option['carrier'] . ": " . $option['total_price'] . "\n";
    }
}
echo "\n";

// Test 3: Non-document 10.5kg Australia  
echo "Test 3: Non-document 10.5kg Australia\n";
echo "  Expected for Singapore-DHL: base 10kg (\$113.85) + 0.5kg (\$3.45) = \$117.30\n";
$quotes = $quoteService->getQuotes('AU', 10.5, 'non_document');
foreach ($quotes['options'] as $option) {
    if (strpos($option['carrier'], 'Singapore') !== false) {
        echo "  " . $option['carrier'] . ": " . $option['total_price'] . "\n";
    }
}
echo "\n";

// Test 4: Non-document 11kg Australia
echo "Test 4: Non-document 11kg Australia\n";
echo "  Expected for Singapore-DHL: base 10kg (\$113.85) + 1kg (\$3.45 × 2) = \$120.75\n";
$quotes = $quoteService->getQuotes('AU', 11.0, 'non_document');
foreach ($quotes['options'] as $option) {
    if (strpos($option['carrier'], 'Singapore-DHL') !== false) {
        echo "  " . $option['carrier'] . ": " . $option['total_price'] . "\n";
    }
}
