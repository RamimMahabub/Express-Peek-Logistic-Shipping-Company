<?php
// Test Quote API with different scenarios
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\QuoteService;
use App\Models\CountryZone;
use App\Models\Carrier;

$quoteService = $app->make(QuoteService::class);

echo "=== QUOTE SERVICE TESTS ===\n\n";

// Test 1: Document 0.5kg Australia S-DHL
echo "Test 1: Document 0.5kg Australia (expecting Singapore-DHL at \$21.28)\n";
$quotes = $quoteService->getQuotes('AU', 0.5, 'document');
echo "  Cheapest: " . $quotes['cheapest']['carrier'] . " - " . $quotes['cheapest']['total_price'] . " " . $quotes['cheapest']['currency'] . "\n\n";

// Test 2: Document 1.5kg Australia S-DHL
echo "Test 2: Document 1.5kg Australia (expecting Singapore-DHL at \$36.80 = 27.14 + 9.66)\n";
$quotes = $quoteService->getQuotes('AU', 1.5, 'document');
echo "  Cheapest: " . $quotes['cheapest']['carrier'] . " - " . $quotes['cheapest']['total_price'] . " " . $quotes['cheapest']['currency'] . "\n\n";

// Test 3: Document 2.0kg Australia S-DHL
echo "Test 3: Document 2.0kg Australia (expecting Singapore-DHL at \$46.46 = 27.14 + 2×9.66)\n";
$quotes = $quoteService->getQuotes('AU', 2.0, 'document');
echo "  Cheapest: " . $quotes['cheapest']['carrier'] . " - " . $quotes['cheapest']['total_price'] . " " . $quotes['cheapest']['currency'] . "\n\n";

// Test 4: Non-document 5kg Australia S-DHL
echo "Test 4: Non-document 5kg Australia (expecting Singapore-DHL at \$65.55)\n";
$quotes = $quoteService->getQuotes('AU', 5.0, 'non_document');
echo "  Cheapest: " . $quotes['cheapest']['carrier'] . " - " . $quotes['cheapest']['total_price'] . " " . $quotes['cheapest']['currency'] . "\n\n";

// Test 5: Non-document 10.5kg Australia S-DHL (testing per_0_5_kg)
echo "Test 5: Non-document 10.5kg Australia (expecting Singapore-DHL at base 10kg + 1×\$3.45)\n";
$quotes = $quoteService->getQuotes('AU', 10.5, 'non_document');
echo "  Cheapest: " . $quotes['cheapest']['carrier'] . " - " . $quotes['cheapest']['total_price'] . " " . $quotes['cheapest']['currency'] . "\n\n";

// Test 6: Non-document 21kg Australia S-DHL
echo "Test 6: Non-document 21kg Australia (expecting Singapore-DHL per-kg rate of \$7.76)\n";
$quotes = $quoteService->getQuotes('AU', 21.0, 'non_document');
echo "  Cheapest: " . $quotes['cheapest']['carrier'] . " - " . $quotes['cheapest']['total_price'] . " " . $quotes['cheapest']['currency'] . "\n\n";
