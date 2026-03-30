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
    Route::post('/leads', [LeadController::class, 'store'])->name('crm.leads.store');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('crm.leads.show');
    Route::patch('/leads/{lead}', [LeadController::class, 'update'])->name('crm.leads.update');
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->name('crm.leads.destroy');
    Route::patch('/leads/{lead}/stage', [LeadController::class, 'moveStage'])->name('crm.leads.stage');
    Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('crm.leads.convert');
    Route::match(['post', 'patch'], '/leads/{lead}/contact', [LeadController::class, 'upsertContact'])->name('crm.leads.contact');
    Route::patch('/leads/{lead}/deal', [LeadController::class, 'attachDeal'])->name('crm.leads.deal');
    Route::post('/leads/{lead}/tasks', [LeadController::class, 'storeTask'])->name('crm.leads.tasks.store');

    Route::get('/deals', [DealController::class, 'index'])->name('crm.deals.index');
    Route::post('/deals', [DealController::class, 'store'])->name('crm.deals.store');
    Route::get('/deals/{deal}', [DealController::class, 'show'])->name('crm.deals.show');
    Route::patch('/deals/{deal}', [DealController::class, 'update'])->name('crm.deals.update');
    Route::delete('/deals/{deal}', [DealController::class, 'destroy'])->name('crm.deals.destroy');
    Route::patch('/deals/{deal}/stage', [DealController::class, 'moveStage'])->name('crm.deals.stage');
    Route::match(['post', 'patch'], '/deals/{deal}/contact', [DealController::class, 'upsertContact'])->name('crm.deals.contact');
    Route::patch('/deals/{deal}/lead', [DealController::class, 'attachLead'])->name('crm.deals.lead');
    Route::patch('/deals/{deal}/analyses', [DealController::class, 'updateAnalyses'])->name('crm.deals.analyses');
    Route::post('/deals/{deal}/tasks', [DealController::class, 'storeTask'])->name('crm.deals.tasks.store');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
