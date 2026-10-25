<?php

use App\Http\Controllers\LaundryController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('laundry.index'))->name('home');
Route::get('/dashboard', fn () => redirect()->route('laundry.index'))->middleware('auth')->name('dashboard');
Route::middleware('auth')->group(function () {
    Route::get('/laundry', [LaundryController::class, 'index'])->name('laundry.index');
    Route::post('/laundry/orders', [LaundryController::class, 'store'])->middleware('throttle:10,1')->name('laundry.orders.store');
    Route::get('/laundry/orders/{order}', [LaundryController::class, 'show'])->name('laundry.orders.show');
    Route::patch('/laundry/orders/{order}/cancel', [LaundryController::class, 'cancel'])->name('laundry.orders.cancel');
    Route::get('/staff/laundry', [LaundryController::class, 'staff'])->name('laundry.staff');
    Route::patch('/staff/laundry/orders/{order}', [LaundryController::class, 'update'])->name('laundry.orders.update');
    Route::get('/admin/laundry/services', [LaundryController::class, 'services'])->name('laundry.services');
    Route::post('/admin/laundry/services', [LaundryController::class, 'saveService'])->name('laundry.services.store');
    Route::put('/admin/laundry/services/{service}', [LaundryController::class, 'saveService'])->name('laundry.services.update');
    Route::get('/admin/laundry/reports', [LaundryController::class, 'reports'])->name('laundry.reports');
    Route::get('/admin/laundry/orders.csv', [LaundryController::class, 'csv'])->name('laundry.csv');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
require __DIR__.'/auth.php';
