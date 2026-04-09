<?php

namespace App\Services;

use App\Models\Carrier;
use App\Models\CountryZone;
use App\Models\Rate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class MonthlyRateImportService
{
    /**
     * Parse both monthly Excel files and import rates in one transaction.
     *
     * @return array<string, mixed>
     */
    public function importFromExcelFiles(string $dhlFilePath, ?string $dhlZoneFilePath, string $masterFilePath): array
    {
        $parsed = $this->parseExcelFiles($dhlFilePath, $dhlZoneFilePath, $masterFilePath);

        return DB::transaction(function () use ($parsed, $dhlFilePath, $dhlZoneFilePath, $masterFilePath) {
            $summary = [
                'dhl' => [
                    'deleted' => 0,
                    'inserted' => 0,
                    'zones' => 0,
                    'weights' => 0,
                    'per_kg_bands' => 0,
                ],
                'dhl_zones' => [
                    'deleted' => 0,
                    'inserted' => 0,
                    'countries' => 0,
                    'skipped' => 0,
                ],
                'master' => [
                    'deleted' => 0,
                    'inserted' => 0,
                    'providers' => 0,
                    'countries' => 0,
                    'skipped_countries' => 0,
                ],
            ];

            if ($dhlZoneFilePath !== null) {
                $summary['dhl_zones'] = $this->importDhlZones($parsed['dhl_zones'] ?? []);
            } else {
                $summary['dhl_zones']['skipped'] = 1;
            }
            $summary['dhl'] = $this->importDhlRates($parsed['dhl'] ?? []);
            $summary['master'] = $this->importMasterRates($parsed['master'] ?? []);

            Log::info('Monthly rate import completed.', [
                'dhl_file' => basename($dhlFilePath),
                'master_file' => basename($masterFilePath),
                'summary' => $summary,
            ]);

            return $summary;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function parseExcelFiles(string $dhlRateFilePath, ?string $dhlZoneFilePath, string $masterFilePath): array
    {
        $tempOutputPath = storage_path('app/rate-import/latest_parsed_rates.json');
        $outputDir = dirname($tempOutputPath);

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $pythonBinary = $this->resolvePythonBinary();
        $scriptPath = base_path('parse_monthly_rates.py');

        if (!file_exists($scriptPath)) {
            throw new RuntimeException('Rate parser script not found: parse_monthly_rates.py');
        }

        $processArgs = [
            $pythonBinary,
            $scriptPath,
            '--dhl-rate',
            $dhlRateFilePath,
        ];

        if ($dhlZoneFilePath !== null) {
            $processArgs[] = '--dhl-zone';
            $processArgs[] = $dhlZoneFilePath;
        }

        $processArgs[] = '--master';
        $processArgs[] = $masterFilePath;
        $processArgs[] = '--output';
        $processArgs[] = $tempOutputPath;

        $process = new Process($processArgs);

        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Excel parsing failed: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        $parsed = json_decode((string) file_get_contents($tempOutputPath), true);

        if (!is_array($parsed) || !isset($parsed['dhl'], $parsed['master'])) {
            throw new RuntimeException('Parser returned invalid JSON structure.');
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $zoneData
     * @return array<string, int>
     */
    private function importDhlZones(array $zoneData): array
    {
        $carrier = Carrier::firstOrCreate(
            ['name' => 'DHL-Bangladesh'],
            [
                'fuel_surcharge_percent' => 0,
                'profit_margin_percent' => 0,
                'currency' => 'BDT',
            ]
        );

        // Keep only the latest DHL-specific zone map.
        $deleted = CountryZone::where('carrier_id', $carrier->id)->delete();

        $inserted = 0;
        foreach (($zoneData['zones'] ?? []) as $zoneRow) {
            $countryCode = strtoupper((string) ($zoneRow['country_code'] ?? ''));
            $countryName = trim((string) ($zoneRow['country_name'] ?? ''));
            $zone = isset($zoneRow['zone']) ? (int) $zoneRow['zone'] : null;

            if ($countryCode === '' || $countryName === '' || $zone === null) {
                continue;
            }

            CountryZone::updateOrCreate(
                [
                    'country_code' => $countryCode,
                    'carrier_id' => $carrier->id,
                ],
                [
                    'country_name' => $countryName,
                    'zone' => $zone,
                ]
            );
            $inserted++;
        }

        return [
            'deleted' => $deleted,
            'inserted' => $inserted,
            'countries' => $inserted,
        ];
    }

    /**
     * @param array<string, mixed> $dhlData
     * @return array<string, int>
     */
    private function importDhlRates(array $dhlData): array
    {
        $carrier = Carrier::firstOrCreate(
            ['name' => 'DHL-Bangladesh'],
            [
                'fuel_surcharge_percent' => 0,
                'profit_margin_percent' => 0,
                'currency' => 'BDT',
            ]
        );

        $deleted = Rate::where('carrier_id', $carrier->id)
            ->whereNotNull('zone')
            ->delete();

        $inserted = 0;

        $documentRows = $dhlData['document'] ?? [];
        foreach ($documentRows as $row) {
            $weight = (float) ($row['weight'] ?? 0);
            $zones = $row['zones'] ?? [];

            foreach ($zones as $zone => $price) {
                if ($price === null) {
                    continue;
                }

                Rate::create([
                    'carrier_id' => $carrier->id,
                    'zone' => (int) $zone,
                    'country_code' => null,
                    'country_name' => null,
                    'shipment_type' => 'document',
                    'weight_slab' => $weight,
                    'price' => (float) $price,
                    'per_kg_rate' => null,
                    'rate_type' => null,
                ]);
                $inserted++;
            }
        }

        $nonDocumentRows = $dhlData['non_document'] ?? [];
        foreach ($nonDocumentRows as $row) {
            $weight = (float) ($row['weight'] ?? 0);
            $zones = $row['zones'] ?? [];

            foreach ($zones as $zone => $price) {
                if ($price === null) {
                    continue;
                }

                Rate::create([
                    'carrier_id' => $carrier->id,
                    'zone' => (int) $zone,
                    'country_code' => null,
                    'country_name' => null,
                    'shipment_type' => 'non_document',
                    'weight_slab' => $weight,
                    'price' => (float) $price,
                    // The 30kg slab stores the default per-kg rate fallback if present.
                    'per_kg_rate' => ($weight === 30.0)
                        ? $this->firstPerKgBandRateForZone($dhlData['per_kg_bands'] ?? [], (int) $zone)
                        : null,
                    'rate_type' => null,
                ]);
                $inserted++;
            }
        }

        foreach (($dhlData['per_kg_bands'] ?? []) as $band) {
            $from = isset($band['from']) ? (float) $band['from'] : null;
            $zones = $band['zones'] ?? [];

            if ($from === null) {
                continue;
            }

            foreach ($zones as $zone => $perKgRate) {
                if ($perKgRate === null) {
                    continue;
                }

                Rate::create([
                    'carrier_id' => $carrier->id,
                    'zone' => (int) $zone,
                    'country_code' => null,
                    'country_name' => null,
                    'shipment_type' => 'non_document',
                    'weight_slab' => $from,
                    'price' => 0,
                    'per_kg_rate' => (float) $perKgRate,
                    'rate_type' => 'per_kg',
                ]);
                $inserted++;
            }
        }

        return [
            'deleted' => $deleted,
            'inserted' => $inserted,
            'zones' => count(array_keys($documentRows[0]['zones'] ?? ($nonDocumentRows[0]['zones'] ?? []))),
            'weights' => count($documentRows) + count($nonDocumentRows),
            'per_kg_bands' => count($dhlData['per_kg_bands'] ?? []),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $bands
     */
    private function firstPerKgBandRateForZone(array $bands, int $zone): ?float
    {
        if (empty($bands)) {
            return null;
        }

        $firstBand = $bands[0];
        $zones = $firstBand['zones'] ?? [];

        if (!isset($zones[$zone])) {
            return null;
        }

        return (float) $zones[$zone];
    }

    /**
     * @param array<string, mixed> $masterData
     * @return array<string, int>
     */
    private function importMasterRates(array $masterData): array
    {
        $providerMap = [
            'Singapore-DHL' => 'Singapore-DHL',
            'Singapore-UPS' => 'Singapore-UPS',
            'DUBAI-DHL' => 'DUBAI-DHL',
            'DUBAI-UPS' => 'DUBAI-UPS',
            'Singapore-FedEx' => 'Singapore-FedEx',
            'DUBAI-FedEx' => 'DUBAI-FedEx',
            'Master' => 'Master',
            'Master-SF' => 'Master-SF',
            'Master-Nice' => 'Master-Nice',
            'DUBAI-DHL/Risk' => 'DUBAI-DHL/Risk',
        ];

        $countryMap = $this->countryCodeMap();
        $ignoredCountryNames = $this->ignoredCountryNames();

        $inserted = 0;
        $deleted = 0;
        $countries = 0;
        $skippedCountries = 0;

        $providers = $masterData['providers'] ?? [];

        foreach ($providers as $providerName => $providerData) {
            if (!isset($providerMap[$providerName])) {
                continue;
            }

            $carrier = Carrier::firstOrCreate(
                ['name' => $providerMap[$providerName]],
                [
                    'fuel_surcharge_percent' => 0,
                    'profit_margin_percent' => 0,
                    'currency' => 'USD',
                ]
            );

            // Latest-only semantics: replace all existing country-based rates for this provider.
            $deleted += Rate::where('carrier_id', $carrier->id)
                ->whereNull('zone')
                ->delete();

            foreach (($providerData['countries'] ?? []) as $countryName => $countryData) {
                $normalizedCountryName = trim((string) $countryName);
                $normalizedKey = mb_strtoupper($normalizedCountryName);

                if (in_array($normalizedKey, $ignoredCountryNames, true)) {
                    $skippedCountries++;
                    continue;
                }

                $countryCode = $countryMap[$normalizedKey] ?? null;
                $countries++;

                $inserted += $this->insertMasterCountryRates($carrier->id, $normalizedCountryName, $countryCode, is_array($countryData) ? $countryData : []);
            }
        }

        return [
            'deleted' => $deleted,
            'inserted' => $inserted,
            'providers' => count($providers),
            'countries' => $countries,
            'skipped_countries' => $skippedCountries,
        ];
    }

    /**
     * @param array<string, mixed> $countryData
     */
    private function insertMasterCountryRates(int $carrierId, string $countryName, ?string $countryCode, array $countryData): int
    {
        $inserted = 0;

        $document = is_array($countryData['document'] ?? null) ? $countryData['document'] : [];
        $doc_0_5 = isset($document['0.5']) ? (float) $document['0.5'] : null;
        $doc_1_0 = isset($document['1.0']) ? (float) $document['1.0'] : null;
        $doc_add_0_5 = isset($document['add_0.5']) ? (float) $document['add_0.5'] : null;

        if ($doc_0_5 !== null && $doc_0_5 > 0) {
            Rate::create([
                'carrier_id' => $carrierId,
                'zone' => null,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'shipment_type' => 'document',
                'weight_slab' => 0.5,
                'price' => $doc_0_5,
                'per_kg_rate' => null,
                'rate_type' => null,
            ]);
            $inserted++;
        }

        if ($doc_1_0 !== null && $doc_1_0 > 0) {
            Rate::create([
                'carrier_id' => $carrierId,
                'zone' => null,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'shipment_type' => 'document',
                'weight_slab' => 1.0,
                'price' => $doc_1_0,
                'per_kg_rate' => null,
                'rate_type' => null,
            ]);
            $inserted++;

            if ($doc_add_0_5 !== null && $doc_add_0_5 > 0) {
                for ($increments = 1; $increments <= 8; $increments++) {
                    $weight = 1.0 + ($increments * 0.5);
                    if ($weight > 5.0) {
                        break;
                    }

                    Rate::create([
                        'carrier_id' => $carrierId,
                        'zone' => null,
                        'country_code' => $countryCode,
                        'country_name' => $countryName,
                        'shipment_type' => 'document',
                        'weight_slab' => $weight,
                        'price' => $doc_1_0 + ($increments * $doc_add_0_5),
                        'per_kg_rate' => null,
                        'rate_type' => null,
                    ]);
                    $inserted++;
                }
            }
        }

        foreach (($countryData['parcel'] ?? []) as $weight => $price) {
            if ($price === null || (float) $price <= 0) {
                continue;
            }

            Rate::create([
                'carrier_id' => $carrierId,
                'zone' => null,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'shipment_type' => 'non_document',
                'weight_slab' => (float) $weight,
                'price' => (float) $price,
                'per_kg_rate' => null,
                'rate_type' => null,
            ]);
            $inserted++;
        }

        if (!empty($countryData['per_0_5_kg'])) {
            Rate::create([
                'carrier_id' => $carrierId,
                'zone' => null,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'shipment_type' => 'non_document',
                'weight_slab' => 10.5,
                'price' => 0,
                'per_kg_rate' => (float) $countryData['per_0_5_kg'],
                'rate_type' => 'per_0_5_kg',
            ]);
            $inserted++;
        }

        if (!empty($countryData['per_21_kg'])) {
            Rate::create([
                'carrier_id' => $carrierId,
                'zone' => null,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'shipment_type' => 'non_document',
                'weight_slab' => 21.0,
                'price' => 0,
                'per_kg_rate' => (float) $countryData['per_21_kg'],
                'rate_type' => 'per_kg',
            ]);
            $inserted++;
        }

        if (!empty($countryData['per_31_kg'])) {
            Rate::create([
                'carrier_id' => $carrierId,
                'zone' => null,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'shipment_type' => 'non_document',
                'weight_slab' => 31.0,
                'price' => 0,
                'per_kg_rate' => (float) $countryData['per_31_kg'],
                'rate_type' => 'per_kg',
            ]);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * @return array<string, string>
     */
    private function countryCodeMap(): array
    {
        $raw = [
            'Australia' => 'AU',
            'Brunei' => 'BN',
            'China' => 'CN',
            'Cambodia' => 'KH',
            'Indonesia' => 'ID',
            'Japan' => 'JP',
            'Macau' => 'MO',
            'Malaysia' => 'MY',
            'Myanmar' => 'MM',
            'Newzeland' => 'NZ',
            'New Zealand' => 'NZ',
            'Pakistan' => 'PK',
            'Phillipines' => 'PH',
            'Philippines' => 'PH',
            'Singapore' => 'SG',
            'South Korea' => 'KR',
            'Korea' => 'KR',
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
            'UAE Others' => 'AE',
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
            'GREECE' => 'GR',
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
            'South Africa' => 'ZA',
            'Monaco' => 'MC',
            'Nepal' => 'NP',
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
            'Maldives' => 'MV',
            'Tanzania' => 'TZ',
            'Tunisia' => 'TN',
            'Uganda' => 'UG',
            'Uzbekistan' => 'UZ',
            'Zambia' => 'ZM',
            'Zimbabwe' => 'ZW',
            'Uruguay' => 'UY',
            'Hong Kong' => 'HK',
            'Bhutan' => 'BT',
            'Seychelles' => 'SC',
        ];

        $mapped = [];
        foreach ($raw as $countryName => $code) {
            $mapped[mb_strtoupper(trim($countryName))] = $code;
        }

        return $mapped;
    }

    /**
     * @return array<int, string>
     */
    private function ignoredCountryNames(): array
    {
        return [
            'DHL RISK FEE APPLY',
            'IRAQ-RISK FEE APPLY',
            'LIBYA-RISK FEE APPLY',
            'REST OF THE WORLD',
            'SOMALIA-RISK FEE APP',
            'SUDAN-RISK FEE APPL',
            'SYRIA - RISK FEE APPLY',
            'YEMEN-RISK FEE APPLY',
            'LEBANON -RISK FEE',
            'UPS EXTRA $4.6 APPLY',
            'USA -N.Y/ N.J/CA',
            'DUBAI,SHARJAH',
        ];
    }

    private function resolvePythonBinary(): string
    {
        $candidates = array_values(array_filter([
            env('PYTHON_BINARY'),
            'python',
            'py',
            'C:/Users/Admin/AppData/Local/Programs/Python/Python311/python.exe',
        ]));

        foreach ($candidates as $candidate) {
            $process = new Process([$candidate, '--version']);
            $process->setTimeout(10);
            $process->run();

            if ($process->isSuccessful()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Python executable not found. Set PYTHON_BINARY in .env.');
    }
}
