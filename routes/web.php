<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\BarcodePrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));
Route::middleware('guest')->group(function () {
    Route::get('/login',[AuthController::class,'login'])->name('login');
    Route::post('/login',[AuthController::class,'authenticate'])->middleware('throttle:5,1')->name('login.store');
    Route::post('/quick-login',[AuthController::class,'quickLogin'])->middleware('throttle:5,1')->name('login.pin');
    Route::get('/setup',[AuthController::class,'register'])->name('setup');
    Route::post('/setup',[AuthController::class,'storeShop'])->name('setup.store');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout',[AuthController::class,'logout'])->name('logout');
    Route::get('/profile',[App\Http\Controllers\ProfileController::class,'edit'])->name('profile.edit');
    Route::put('/profile',[App\Http\Controllers\ProfileController::class,'update'])->name('profile.update');
    Route::get('/facility',[App\Http\Controllers\FacilityController::class,'edit'])->name('facility.edit');
    Route::get('/facility/logo/{path}',[App\Http\Controllers\FacilityController::class,'logo'])->where('path','.*')->name('facility.logo');
    Route::put('/facility',[App\Http\Controllers\FacilityController::class,'update'])->middleware('role:owner')->name('facility.update');
    Route::get('/dashboard',DashboardController::class)->name('dashboard');
    Route::get('/products',[ProductController::class,'index'])->middleware('permission:products')->name('products.index');
    Route::middleware('permission:products')->group(function () {
        Route::get('/products/create',[ProductController::class,'create'])->name('products.create');
        Route::post('/products',[ProductController::class,'store'])->name('products.store');
        Route::get('/products/{product}/edit',[ProductController::class,'edit'])->name('products.edit');
        Route::put('/products/{product}',[ProductController::class,'update'])->name('products.update');
        Route::post('/products/{product}/variants',[ProductController::class,'addVariant'])->name('products.variants.store');
        Route::put('/product-variants/{variant}',[ProductController::class,'updateVariant'])->name('variants.update');
        Route::post('/product-variants/{variant}/print-barcode',[BarcodePrintController::class,'print'])->name('variants.print-barcode');
        Route::post('/products/{product}/archive',[ProductController::class,'archive'])->name('products.archive');
    });
    Route::get('/products/search',[ProductController::class,'search'])->middleware('permission:pos,products,purchases,sales')->name('products.search');
    Route::get('/products/{product}',[ProductController::class,'show'])->middleware('permission:products')->name('products.show');
    Route::middleware('role:owner')->group(function () {
        Route::get('/masters/bill-setting',[App\Http\Controllers\BillSettingController::class,'edit'])->name('masters.bill-settings.edit');
        Route::put('/masters/bill-setting',[App\Http\Controllers\BillSettingController::class,'update'])->name('masters.bill-settings.update');
        Route::get('/masters/receipt-template',[App\Http\Controllers\ReceiptTemplateController::class,'edit'])->name('masters.receipt-template.edit');
        Route::put('/masters/receipt-template',[App\Http\Controllers\ReceiptTemplateController::class,'update'])->name('masters.receipt-template.update');
        Route::get('/masters/roles',[RoleController::class,'index'])->name('masters.roles.index');
        Route::get('/masters/roles/create',[RoleController::class,'create'])->name('masters.roles.create');
        Route::post('/masters/roles',[RoleController::class,'store'])->name('masters.roles.store');
        Route::get('/masters/roles/{role}/edit',[RoleController::class,'edit'])->name('masters.roles.edit');
        Route::put('/masters/roles/{role}',[RoleController::class,'update'])->name('masters.roles.update');
        Route::delete('/masters/roles/{role}',[RoleController::class,'destroy'])->name('masters.roles.destroy');
        Route::get('/users',[UserController::class,'index'])->name('users.index');
        Route::get('/users/create',[UserController::class,'create'])->name('users.create');
        Route::get('/users/{user}/edit',[UserController::class,'edit'])->name('users.edit');
        Route::put('/users/{user}',[UserController::class,'update'])->name('users.update');
        Route::post('/users',[UserController::class,'store'])->name('users.store');
        Route::get('/audit',[AuditController::class,'index'])->name('audit.index');
        Route::get('/backups',[BackupController::class,'index'])->name('backups.index');
        Route::post('/backups',[BackupController::class,'create'])->name('backups.create');
        Route::post('/backups/upload',[BackupController::class,'upload'])->name('backups.upload');
        Route::post('/backups/restore',[BackupController::class,'restore'])->name('backups.restore');
    });
    Route::middleware('permission:inventory')->group(function () {
        Route::get('/inventory',[InventoryController::class,'index'])->name('inventory.index');
        Route::get('/inventory/movements',[InventoryController::class,'movements'])->name('inventory.movements');
        Route::get('/inventory/movements/export',[InventoryController::class,'exportMovements'])->name('inventory.movements.export');
        Route::get('/inventory/stock-status',[InventoryController::class,'stockStatus'])->name('inventory.stock-status');
        Route::get('/inventory/stock-status/export',[InventoryController::class,'exportStockStatus'])->name('inventory.stock-status.export');
    });
    Route::post('/inventory/{variant}/adjust',[InventoryController::class,'adjust'])->middleware('permission:inventory')->name('inventory.adjust');
    Route::get('/reports/sales',[ReportController::class,'sales'])->middleware('permission:reports')->name('reports.sales');
    Route::get('/customers',[CustomerController::class,'index'])->middleware('permission:customers')->name('customers.index');
    Route::get('/customers/export',[CustomerController::class,'export'])->middleware('permission:customers')->name('customers.export');
    Route::get('/customers/search',[CustomerController::class,'search'])->middleware('permission:pos,customers')->name('customers.search');
    Route::get('/customers/{customer}',[CustomerController::class,'show'])->middleware('permission:customers')->name('customers.show');
    Route::middleware('permission:customers')->group(function () {
        Route::post('/customers',[CustomerController::class,'store'])->name('customers.store');
        Route::post('/customers/{customer}/payments',[CustomerController::class,'collect'])->name('customers.payments.store');
    });
    Route::get('/expenses',[ExpenseController::class,'index'])->middleware('permission:expenses')->name('expenses.index');
    Route::post('/expenses',[ExpenseController::class,'store'])->middleware('permission:expenses')->name('expenses.store');
    Route::middleware('auth')->group(function () {
        Route::get('/suppliers',[SupplierController::class,'index'])->middleware('permission:suppliers')->name('suppliers.index');
        Route::post('/suppliers',[SupplierController::class,'store'])->middleware('permission:suppliers')->name('suppliers.store');
        Route::get('/suppliers/{supplier}',[SupplierController::class,'show'])->middleware('permission:suppliers')->name('suppliers.show');
        Route::post('/suppliers/{supplier}/payments',[SupplierController::class,'collect'])->middleware('permission:suppliers')->name('suppliers.payments.store');
        Route::get('/purchases',[PurchaseController::class,'index'])->middleware('permission:purchases')->name('purchases.index');
        Route::get('/purchases/create',[PurchaseController::class,'create'])->middleware('permission:purchases')->name('purchases.create');
        Route::post('/purchases',[PurchaseController::class,'store'])->middleware('permission:purchases')->name('purchases.store');
    });
    Route::get('/pos',[SaleController::class,'index'])->middleware('permission:pos')->name('pos');
    Route::post('/sales',[SaleController::class,'store'])->middleware('permission:pos')->name('sales.store');
    Route::get('/sales',[SaleController::class,'history'])->middleware('permission:sales')->name('sales.index');
    Route::get('/sales/{sale}/receipt',[SaleController::class,'receipt'])->middleware('permission:sales,pos')->name('sales.receipt');
    Route::get('/returns',[ReturnController::class,'index'])->middleware('permission:sales,returns')->name('returns.index');
    Route::get('/sales/{sale}/returns/create',[ReturnController::class,'create'])->middleware('permission:sales,returns')->name('returns.create');
    Route::post('/sales/{sale}/returns',[ReturnController::class,'store'])->middleware('permission:sales,returns')->name('returns.store');
});
