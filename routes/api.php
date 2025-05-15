<?php

use App\Http\Controllers\ProductsController;
use Illuminate\Support\Facades\Route;

Route::post('/products', [ProductsController::class, 'store']);
Route::post('/products/keep', [ProductsController::class, 'keep']);
