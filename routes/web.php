<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CprController;

Route::get('/', [CprController::class, 'index'])->name('cpr.index');
Route::post('/scan', [CprController::class, 'scan'])->name('cpr.scan');
Route::get('/open-pdf', [CprController::class, 'openPdf'])->name('cpr.open');
Route::get('/edit/{id}', [CprController::class, 'edit'])->name('cpr.edit');
Route::post('/update/{id}', [CprController::class, 'update'])->name('cpr.update');
Route::get('/cpr/progress', [CprController::class, 'progress'])->name('cpr.progress');
Route::get('/results', [CprController::class, 'results'])->name('cpr.results');
Route::get('/results', [CprController::class, 'results'])->name('cpr.results');