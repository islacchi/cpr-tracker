<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CprController;

Route::get('/', [CprController::class, 'index'])->name('cpr.index');
Route::post('/scan', [CprController::class, 'scan'])->name('cpr.scan');
Route::get('/open-pdf', [CprController::class, 'openPdf'])->name('cpr.open');