<?php 
use App\Http\Controllers\ProductController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::post('/api/get-token', [TokenController::class, 'getToken']);

Route::get('/api/product', [ProductController::class, 'index'])->middleware('custom.auth');
Route::post('/api/product', [ProductController::class, 'store'])->middleware('custom.auth');
Route::get('/api/variant', [ProductController::class, 'getVariant'])->middleware('custom.auth');
Route::post('/api/inventory', [InventoryController::class, 'store'])->middleware('custom.auth');
Route::post('/api/inventory/abs', [InventoryController::class, 'setAbsoluteValue'])->middleware('custom.auth');

Route::post('/api/order/create', [OrderController::class, 'store']);//->middleware('verify.shopify.webhook');
Route::post('/api/order/update', [OrderController::class, 'update']);

Route::post('/api/send-order/{id}', [OrderController::class, 'resendOrder'])->middleware(['auth:sanctum', 'verified'])->name('order.resend');