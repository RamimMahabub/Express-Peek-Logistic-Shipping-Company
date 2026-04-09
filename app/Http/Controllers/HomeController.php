<?php

namespace App\Http\Controllers;

use App\Services\ShipmentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __construct(private ShipmentService $shipmentService)
    {
    }

    public function index()
    {
        $stats = ['total' => 0, 'pending' => 0, 'in_transit' => 0, 'delivered' => 0];
        $recentShipments = collect();

        if (auth()->check() && auth()->user()->isCustomer()) {
            $shipments = $this->shipmentService->getShipmentsForUser(auth()->user());
            $recentShipments = $shipments->take(5);
            $stats = [
                'total' => $shipments->count(),
                'pending' => $shipments->where('status', 'pending')->count(),
                'in_transit' => $shipments->where('status', 'in_transit')->count(),
                'delivered' => $shipments->where('status', 'delivered')->count(),
            ];
        }

        $countries = \App\Models\CountryZone::query()
            ->select('country_name', DB::raw('MIN(country_code) as country_code'))
            ->whereNotNull('country_name')
            ->whereNotNull('country_code')
            ->groupBy('country_name')
            ->orderBy('country_name')
            ->get();

        return view('home', compact('stats', 'recentShipments', 'countries'));
    }

    public function track(\Illuminate\Http\Request $request)
    {
        $trackingNumber = $request->query('tracking');
        $shipment = null;
        $error = null;

        if ($trackingNumber) {
            $shipment = $this->shipmentService->findByTrackingNumber(trim($trackingNumber));
            if (!$shipment) {
                $error = "No shipment found for tracking number: {$trackingNumber}";
            }
        }

        return view('tracking', compact('shipment', 'trackingNumber', 'error'));
    }
}
