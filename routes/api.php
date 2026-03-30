<?php

use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\ReferenceDataController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/pipelines', [PipelineController::class, 'index']);
        Route::get('/pipelines/{pipeline}/stages', [PipelineController::class, 'stages']);
        Route::get('/analyses', [AnalysisController::class, 'index']);
        Route::get('/reference-data', [ReferenceDataController::class, 'index']);

        Route::apiResource('leads', LeadController::class);
        Route::post('/leads/{lead}/stage', [LeadController::class, 'moveStage']);
        Route::post('/leads/{lead}/convert', [LeadController::class, 'convert']);

        Route::apiResource('deals', DealController::class);
        Route::post('/deals/{deal}/stage', [DealController::class, 'moveStage']);

        Route::apiResource('contacts', ContactController::class)->only([
            'index', 'store', 'show', 'update',
        ]);

        Route::apiResource('tasks', TaskController::class)->except(['show']);
    });
});