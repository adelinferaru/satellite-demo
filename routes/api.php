<?php

use App\Http\Controllers\IssController;
use Illuminate\Support\Facades\Route;

Route::get('satellites', [IssController::class, 'satellites'])->name('satellites');
Route::get('satellite/{id?}', [IssController::class, 'satelliteId'])->name('satellite.id');
Route::get('satellite/{id}/positions', [IssController::class, 'satellitePositions'])->name('satellite.positions');
Route::get('coordinates/{lat},{lon}', [IssController::class, 'coordinates'])->name('coordinates');
Route::get('distance/{lat},{lon}', [IssController::class, 'getDistance'])->name('calculate');
