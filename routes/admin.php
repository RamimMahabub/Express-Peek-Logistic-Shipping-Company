<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\RateImportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

        Route::resource('users', AdminUserController::class);

        // Monthly rate uploads
        Route::get('/rates/import', [RateImportController::class, 'create'])->name('rates.import.create');
        Route::post('/rates/import', [RateImportController::class, 'store'])->name('rates.import.store');

        // Shipments management
        Route::get('/shipments', function () {
            $shipments = \App\Models\Shipment::with(['sender', 'agent'])->latest()->paginate(20);
            return view('admin.shipments.index', compact('shipments'));
        })->name('shipments.index');
    });
