<?php

use App\Http\Controllers\Agent\DashboardController as AgentDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:agent'])
    ->prefix('agent')
    ->name('agent.')
    ->group(function () {
        Route::get('/dashboard', [AgentDashboard::class, 'index'])->name('dashboard');

        // Agent shipment queue
        Route::get('/shipments', function () {
            $shipments = auth()->user()->assignedShipments()
                ->with(['sender', 'trackingEvents'])
                ->latest()
                ->paginate(15);
            return view('agent.shipments.index', compact('shipments'));
        })->name('shipments.index');
    });
