<?php

use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ProductsControllerAirvinylrefill;
use App\Http\Controllers\ProductsControllerAirvinyls;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ProductsControllerKeychains;

// 🟢 Correct Route for Keychains: Mapped to storeKeychain method on the dedicated controller
Route::post('/products/keychains', [ProductsControllerKeychains::class, 'storeKeychain']); 

// Existing working routes (assuming they are correct):
Route::post('/products/albumtagz', [ProductsController::class, 'store']);
Route::post('/products/airvinyls', [ProductsControllerAirvinyls::class, 'store']);
Route::post('/products/airvinylrefill', [ProductsControllerAirvinylrefill::class, 'store']);
Route::post('/products/keep', [ProductsController::class, 'keep']);
Route::get('/search', [SearchController::class, 'search']);
