<?php

use App\Http\Controllers\ProductsController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json(['message' => 'It works']);
});


Route::get('/search', [SearchController::class, 'search']);
Route::post('/products', [ProductsController::class, 'store']);
Route::post('/products/keep', [ProductsController::class, 'keep']);
