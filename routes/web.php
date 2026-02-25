<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PurchaseOrderController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/dtr', fn () => Inertia::render('Dtr/Index'))->name('dtr.index');
    Route::get('dtr/months', [DtrMonthController::class, 'index'])->name('dtr.months.index');
    Route::post('dtr/months', [DtrMonthController::class, 'store'])->name('dtr.months.store');
    Route::get('dtr/months/{month}', [DtrMonthController::class, 'show'])->name('dtr.months.show');
    Route::post('dtr/rows', [DtrRowController::class, 'store'])->name('dtr.rows.store');
    Route::patch('dtr/rows/{row}', [DtrRowController::class, 'update'])->name('dtr.rows.update');
    Route::delete('dtr/rows/{row}', [DtrRowController::class, 'destroy'])->name('dtr.rows.destroy');

    // Food Distribution ERP Routes
    Route::resource('products', ProductController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('customers', CustomerController::class);
    Route::resource('orders', OrderController::class);
    Route::resource('purchase-orders', PurchaseOrderController::class);

    // DTR
    Route::resource('dtr-months', DtrMonthController::class);
    Route::resource('dtr-rows', DtrRowController::class);
});

// Admin routes with role middleware
Route::middleware(['auth', 'role:Super Admin|Admin'])->prefix('admin')->name('admin.')->group(function () {
    // User Management
    Route::resource('users', UserController::class);
    
    // Role Management
    Route::resource('roles', RoleController::class);
});

require __DIR__.'/auth.php';
