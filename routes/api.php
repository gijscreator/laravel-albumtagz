<?php

use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ProductsControllerAirvinylrefill;
use App\Http\Controllers\ProductsControllerAirvinyls;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ProductsControllerKeychains;

// ═══════════════════════════════════════════════════════════════
// CORS PREFLIGHT ROUTES (OPTIONS requests)
// ═══════════════════════════════════════════════════════════════
// These must be BEFORE the actual routes to handle preflight requests

Route::options('/api/products/keychains', [ProductsControllerKeychains::class, 'optionsKeychain']);
Route::options('/api/products/keychains/couple', [ProductsControllerKeychains::class, 'optionsCoupleKeychain']);
Route::options('/api/search', function() {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization')
        ->header('Access-Control-Max-Age', '86400');
});

// ═══════════════════════════════════════════════════════════════
// API ROUTES
// ═══════════════════════════════════════════════════════════════

// Keychain routes (with /api prefix)
Route::post('/api/products/keychains', [ProductsControllerKeychains::class, 'storeKeychain']);
Route::post('/api/products/keychains/couple', [ProductsControllerKeychains::class, 'storeCoupleKeychain']);

// Search route (with /api prefix - this is what the frontend calls)
Route::get('/api/search', [SearchController::class, 'search']);

// Other product routes (keep existing paths)
Route::post('/products/albumtagz', [ProductsController::class, 'store']);
Route::post('/products/airvinyls', [ProductsControllerAirvinyls::class, 'store']);
Route::post('/products/airvinylrefill', [ProductsControllerAirvinylrefill::class, 'store']);
Route::post('/products/keep', [ProductsController::class, 'keep']);

// Legacy keychain route (if needed for backward compatibility)
Route::post('/products/keychains', [ProductsControllerKeychains::class, 'storeKeychain']);

