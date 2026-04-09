<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\MonthlyRateImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RateImportFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_upload_both_excel_files_and_receive_success_summary(): void
    {
        Role::findOrCreate('admin', 'web');

        $admin = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        $serviceMock = Mockery::mock(MonthlyRateImportService::class);
        $serviceMock->shouldReceive('importFromExcelFiles')
            ->once()
            ->withArgs(function (string $dhlPath, ?string $dhlZonePath, string $masterPath): bool {
                return str_contains($dhlPath, 'rate-import/uploads/dhl_')
                    && (is_null($dhlZonePath) || str_contains($dhlZonePath, 'rate-import/uploads/dhl_zone_'))
                    && str_contains($masterPath, 'rate-import/uploads/master_');
            })
            ->andReturn([
                'dhl' => [
                    'deleted' => 448,
                    'inserted' => 462,
                    'zones' => 7,
                    'weights' => 64,
                    'per_kg_bands' => 2,
                ],
                'dhl_zones' => [
                    'deleted' => 121,
                    'inserted' => 121,
                    'countries' => 121,
                ],
                'master' => [
                    'deleted' => 8839,
                    'inserted' => 8839,
                    'providers' => 10,
                    'countries' => 421,
                    'skipped_countries' => 12,
                ],
            ]);

        $this->instance(MonthlyRateImportService::class, $serviceMock);

        $dhlFile = UploadedFile::fake()->create(
            'DHL BANGLADESH RATES.xlsx',
            120,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $zoneFile = UploadedFile::fake()->create(
            'DHL BANGLADESH ZONELIST.xlsx',
            120,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $masterFile = UploadedFile::fake()->create(
            'MASTER AIR AGENT RATE 01ST July.2025.xlsx',
            240,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response = $this->actingAs($admin)
            ->from('/admin/rates/import')
            ->post(route('admin.rates.import.store'), [
                'dhl_file' => $dhlFile,
                'dhl_zone_file' => $zoneFile,
                'master_file' => $masterFile,
            ]);

        $response->assertRedirect('/admin/rates/import');
        $response->assertSessionHas('success');
        $response->assertSessionHas('import_summary');

        $summary = session('import_summary');
        $this->assertSame(462, $summary['dhl']['inserted']);
        $this->assertSame(8839, $summary['master']['inserted']);
    }
}
