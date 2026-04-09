<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MonthlyRateImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class RateImportController extends Controller
{
    public function __construct(private MonthlyRateImportService $rateImportService)
    {
    }

    public function create(): View
    {
        return view('admin.rates.import');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dhl_file' => ['required', 'file', 'mimes:xlsx'],
            'dhl_zone_file' => ['required', 'file', 'mimes:xlsx'],
            'master_file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $dhlPath = $validated['dhl_file']->storeAs('rate-import/uploads', 'dhl_' . time() . '.xlsx');
        $dhlZonePath = $validated['dhl_zone_file']->storeAs('rate-import/uploads', 'dhl_zone_' . time() . '.xlsx');
        $masterPath = $validated['master_file']->storeAs('rate-import/uploads', 'master_' . time() . '.xlsx');

        try {
            $summary = $this->rateImportService->importFromExcelFiles(
                storage_path('app/private/' . $dhlPath),
                storage_path('app/private/' . $dhlZonePath),
                storage_path('app/private/' . $masterPath)
            );
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Rate import failed: ' . $e->getMessage());
        }

        $successMessage = sprintf(
            'Rates updated successfully. DHL zones inserted %d rows, DHL inserted %d rows, Master inserted %d rows.',
            $summary['dhl_zones']['inserted'] ?? 0,
            $summary['dhl']['inserted'] ?? 0,
            $summary['master']['inserted'] ?? 0
        );

        return back()->with('success', $successMessage)->with('import_summary', $summary);
    }
}
