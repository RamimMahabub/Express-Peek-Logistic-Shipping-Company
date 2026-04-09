<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Public homepage — DHL-style landing (guests + all authenticated users)
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/track', [HomeController::class, 'track'])->name('track');

// /dashboard redirects admins/agents to their panels; customers and guests go to /
Route::get('/dashboard', function () {
    if (!auth()->check()) return redirect()->route('home');
    $user = auth()->user();
    if ($user->isAdmin()) return redirect()->route('admin.dashboard');
    if ($user->isAgent()) return redirect()->route('agent.dashboard');
    return redirect()->route('home');
})->middleware(['auth', 'verified'])->name('dashboard');

// Profile routes (all authenticated users)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/customer.php';
require __DIR__.'/agent.php';
