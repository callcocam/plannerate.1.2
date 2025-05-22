<?php

use Callcocam\Plannerate\Http\Controllers\Api\AnalysisController;
use Callcocam\Plannerate\Http\Controllers\Api\GondolaController;
use Callcocam\Plannerate\Http\Controllers\Api\LayerController;
use Callcocam\Plannerate\Http\Controllers\Api\PlannerateController;
use Callcocam\Plannerate\Http\Controllers\Api\SectionController;
use Callcocam\Plannerate\Http\Controllers\Api\SegmentController;
use Callcocam\Plannerate\Http\Controllers\Api\ShelfController;
use Illuminate\Support\Facades\Route; 

Route::middleware(['api' ])
    ->prefix('api')
    ->name('api.')
    ->group(function () {

        Route::get('plannerate/{id}', [PlannerateController::class, 'show'])->name('plannerate.show');
        
        Route::put('plannerate/{planogram}', [PlannerateController::class, 'save'])->name('plannerate.save');

        Route::resource('gondolas', GondolaController::class);

        Route::resource('sections', SectionController::class);
        Route::resource('shelves', ShelfController::class);
        Route::resource('segments', SegmentController::class);
        Route::resource('layers', LayerController::class);

        // Rotas de Análise
        Route::prefix('analysis')->group(function () {
            Route::get('/abc', [AnalysisController::class, 'abcAnalysis']);
            Route::get('/target-stock', [AnalysisController::class, 'targetStockAnalysis']);
            Route::get('/bcg', [AnalysisController::class, 'bcgAnalysis']);
        });
    });
//Plannerate