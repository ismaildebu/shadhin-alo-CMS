<?php

use App\Http\Controllers\Admin\AdvertisementController;
use Illuminate\Support\Facades\Route;

// Register this file from the app's routing bootstrap when you choose to activate the module.
Route::middleware(['web', 'auth', 'can:manage-cms'])->prefix('admin/advertisements')->name('advertisements.')->group(function () {
    Route::get('/', [AdvertisementController::class, 'index'])->name('index');
    Route::get('/create', [AdvertisementController::class, 'create'])->name('create');
    Route::post('/', [AdvertisementController::class, 'store'])->name('store');
    Route::get('/{advertisement}/edit', [AdvertisementController::class, 'edit'])->whereNumber('advertisement')->name('edit');
    Route::put('/{advertisement}', [AdvertisementController::class, 'update'])->whereNumber('advertisement')->name('update');
    Route::delete('/{advertisement}', [AdvertisementController::class, 'destroy'])->whereNumber('advertisement')->name('destroy');
});
