<?php

namespace App\Console\Commands;

use App\Services\MonthlyRateImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportMonthlyRates extends Command
{
    protected $signature = 'rates:import-monthly {--dhl= : Path to DHL rate Excel file} {--dhl-zone= : Path to DHL zone Excel file} {--master= : Path to Master Excel file}';

    protected $description = 'Import monthly DHL + Master rates from Excel files in one step';

    public function __construct(private MonthlyRateImportService $rateImportService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dhlPath = $this->option('dhl') ?: base_path('DHL BANGLADESH RATES.xlsx');
        $dhlZonePath = $this->option('dhl-zone') ?: base_path('DHL BANGLADESH ZONELIST.xlsx');
        $masterPath = $this->option('master') ?: base_path('MASTER AIR AGENT RATE 01ST July.2025.xlsx');

        if (!file_exists($dhlPath)) {
            $this->error('DHL file not found: ' . $dhlPath);
            return self::FAILURE;
        }

        if ($dhlZonePath && !file_exists($dhlZonePath)) {
            $this->error('DHL zone file not found: ' . $dhlZonePath);
            return self::FAILURE;
        }

        if (!file_exists($masterPath)) {
            $this->error('Master file not found: ' . $masterPath);
            return self::FAILURE;
        }

        try {
            $summary = $this->rateImportService->importFromExcelFiles($dhlPath, $dhlZonePath, $masterPath);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Monthly rates imported successfully.');
        $this->table(['Source', 'Deleted', 'Inserted'], [
            ['DHL Zones', $summary['dhl_zones']['deleted'] ?? 0, $summary['dhl_zones']['inserted'] ?? 0],
            ['DHL', $summary['dhl']['deleted'] ?? 0, $summary['dhl']['inserted'] ?? 0],
            ['Master', $summary['master']['deleted'] ?? 0, $summary['master']['inserted'] ?? 0],
        ]);

        return self::SUCCESS;
    }
}
