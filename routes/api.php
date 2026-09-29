<?php

use App\Http\Controllers\CreditController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockAdjustmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{id}', [ProductController::class, 'update']);

Route::get('/customers', [CustomerController::class, 'index']);
Route::post('/customers', [CustomerController::class, 'store']);
Route::get('/customers/{id}', [CustomerController::class, 'show']);
Route::get('/customers/{id}/credit-summary', [CreditController::class, 'customerSummary']);

Route::get('/sales', [SaleController::class, 'index']);
Route::post('/sales', [SaleController::class, 'store']);
Route::get('/sales/{id}', [SaleController::class, 'show']);

Route::get('/dashboard/today', [DashboardController::class, 'today']);

Route::get('/reports/customers', [ReportController::class, 'customerReport']);
Route::get('/reports/products', [ReportController::class, 'productReport']);
Route::get('/reports/trends', [ReportController::class, 'trends']);
Route::get('/reports/inventory', [ReportController::class, 'inventoryReport']);

Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store']);

Route::get('/credit-dashboard', [CreditController::class, 'dashboard']);
Route::post('/credit-payments', [CreditController::class, 'storePayment']);
