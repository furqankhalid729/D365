<?php 
use App\Http\Controllers\ProductController;
use App\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

Route::get('/api/product', [ProductController::class, 'index']);
Route::post('/api/product', [ProductController::class, 'store']);
Route::post('/api/inventory', [InventoryController::class, 'store']);