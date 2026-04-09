<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\ShipmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private ShipmentService $shipmentService) {}

    public function index()
    {
        $user      = auth()->user();
        $shipments = $this->shipmentService->getShipmentsForUser($user);

        $stats = [
            'total'      => $shipments->count(),
            'pending'    => $shipments->where('status', 'pending')->count(),
            'in_transit' => $shipments->where('status', 'in_transit')->count(),
            'delivered'  => $shipments->where('status', 'delivered')->count(),
        ];

        $recentShipments = $shipments->take(5);

        $countries = \App\Models\CountryZone::query()
            ->select('country_name', DB::raw('MIN(country_code) as country_code'))
            ->whereNotNull('country_name')
            ->whereNotNull('country_code')
            ->groupBy('country_name')
            ->orderBy('country_name')
            ->get();

        return view('customer.dashboard', compact('stats', 'recentShipments', 'countries'));
    }

    public function track(Request $request)
    {
        $trackingNumber = $request->query('tracking');
        $shipment       = null;
        $error          = null;

        if ($trackingNumber) {
            $shipment = $this->shipmentService->findByTrackingNumber(trim($trackingNumber));
            if (!$shipment) {
                $error = "No shipment found for tracking number: {$trackingNumber}";
            }
        }

        return view('customer.tracking', compact('shipment', 'trackingNumber', 'error'));
    }
}
