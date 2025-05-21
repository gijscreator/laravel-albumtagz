<?php

use App\Http\Controllers\ProductsControllerAlbumtagz;
use App\Http\Controllers\ProductsControllerAirvinyls;
use App\Http\Controllers\ProductsControllerAirvinylrefill;
use App\Http\Controllers\SearchController;

Route::post('/products/albumtagz', [ProductsControllerAlbumtagz::class, 'store']);
Route::post('/products/airvinyls', [ProductsControllerAirvinyls::class, 'store']);
Route::post('/products/airvinylrefill', [ProductsControllerAirvinylrefill::class, 'store']);
Route::post('/products/keep', [ProductsController::class, 'keep']);
Route::get('/search', [SearchController::class, 'search']);
