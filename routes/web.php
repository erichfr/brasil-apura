<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/api/results', [DashboardController::class, 'api'])->middleware('throttle:120,1')->name('results.api');
