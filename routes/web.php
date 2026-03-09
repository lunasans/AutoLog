<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\FuelingController;
use App\Http\Controllers\RepairController;
use Illuminate\Support\Facades\Route;

// Auth Routes
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Protected Routes
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('cars', CarController::class);
    Route::post('cars/{car}/fuelings', [FuelingController::class, 'store'])->name('fuelings.store');
    Route::post('cars/{car}/repairs', [RepairController::class, 'store'])->name('repairs.store');
    Route::delete('fuelings/{fueling}', [FuelingController::class, 'destroy'])->name('fuelings.destroy');
    Route::delete('repairs/{repair}', [RepairController::class, 'destroy'])->name('repairs.destroy');
    
    // Profile & Settings
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('password', [ProfileController::class, 'updatePassword'])->name('password.update');
});
