<?php
// Simple curl test for API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8000/api/quote");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'country' => 'AU',
    'weight' => 1.5,
    'type' => 'document'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "API Test: Document 1.5kg for Australia\n";
echo "HTTP Status: $httpCode\n";
echo "======================================\n\n";

if ($curlError) {
    echo "cURL Error: $curlError\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
    exit;
}

$data = json_decode($response, true);

if (!$data) {
    echo "Failed to parse JSON response\n";
    echo "Raw response: " . substr($response, 0, 500) . "\n";
    exit;
}

if ($data['success'] ?? false) {
    echo "Cheapest option:\n";
    $cheapest = $data['data']['cheapest'];
    printf("  Carrier: %s\n", $cheapest['carrier']);
    printf("  Price: %s %s\n", $cheapest['total_price'], $cheapest['currency']);
    printf("  Base Price: %s %s\n", $cheapest['base_price'], $cheapest['currency']);
    printf("  Weight: %s kg\n", $cheapest['weight']);
    
    echo "\n\nAll options (top 5):\n";
    $count = 0;
    foreach ($data['data']['options'] as $option) {
        if (++$count > 5) break;
        printf("  %s: %s %s\n", $option['carrier'], $option['total_price'], $option['currency']);
    }
} else {
    echo "Error: " . ($data['message'] ?? json_encode($data)) . "\n";
}
