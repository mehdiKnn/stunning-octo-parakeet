<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntreprisesController;
use App\Http\Controllers\PersonnesController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('entreprises', EntreprisesController::class)->name('entreprises');
    Route::get('personnes', PersonnesController::class)->name('personnes');
});

require __DIR__.'/settings.php';
