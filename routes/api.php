<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/dashboard', [ProductController::class, 'dashboard']);
Route::get('/products/sales-report', [ProductController::class, 'salesReport']);

Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/filter', [OrderController::class, 'filterByStatus']);
