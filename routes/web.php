<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Crm\DashboardController;
use App\Http\Controllers\Crm\DealController;
use App\Http\Controllers\Crm\LeadController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('crm.dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('crm.dashboard');

    Route::get('/leads', [LeadController::class, 'index'])->name('crm.leads.index');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('crm.leads.show');
    Route::patch('/leads/{lead}', [LeadController::class, 'update'])->name('crm.leads.update');

    Route::get('/deals', [DealController::class, 'index'])->name('crm.deals.index');
    Route::get('/deals/{deal}', [DealController::class, 'show'])->name('crm.deals.show');
    Route::patch('/deals/{deal}', [DealController::class, 'update'])->name('crm.deals.update');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});