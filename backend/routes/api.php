<?php

use App\Http\Controllers\PartController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuotelineController;
use Illuminate\Support\Facades\Route;

Route::get('/parts', [PartController::class, 'index']);
Route::get('/brands', [PartController::class, 'brands']);
Route::get('/parts/{part}', [PartController::class, 'show']);
Route::apiResource('quote', QuoteController::class, ['only' => ['show', 'create']]);
Route::post('/quote/{quote}/submit', QuoteController::class, 'submit');
Route::apiResource('quote.quoteline', QuotelineController::class);
