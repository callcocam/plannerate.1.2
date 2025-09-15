<?php

use Callcocam\Plannerate\Http\Controllers\Api\AnalysisController;
use Callcocam\Plannerate\Http\Controllers\Api\AnalysisControllerUpdated;
use Callcocam\Plannerate\Http\Controllers\Api\AutoPlanogramController;
use Callcocam\Plannerate\Http\Controllers\Api\GondolaController;
use Callcocam\Plannerate\Http\Controllers\Api\LayerController;
use Callcocam\Plannerate\Http\Controllers\Api\PlannerateController;
use Callcocam\Plannerate\Http\Controllers\Api\SectionController;
use Callcocam\Plannerate\Http\Controllers\Api\SegmentController;
use Callcocam\Plannerate\Http\Controllers\Api\ShelfController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])
    ->prefix('api')
    ->name('api.')
    ->group(function () {

        Route::get('plannerate/{id}', [PlannerateController::class, 'show'])->name('plannerate.show');

        Route::put('plannerate/{planogram}', [PlannerateController::class, 'save'])->name('plannerate.save');

        Route::resource('gondolas', GondolaController::class);



        Route::resource('sections', SectionController::class);
        Route::resource('shelves', ShelfController::class);
        Route::post('shelves/{shelf}/copy', [ShelfController::class, 'shelfCopy'])->name('shelves.copy');
        Route::post('shelves/{shelf}/segment', [ShelfController::class, 'segment'])->name('shelves.segment');
        Route::resource('segments', SegmentController::class);
        Route::resource('layers', LayerController::class);

        // Rotas de Análise
        Route::prefix('analysis')->group(function () {
            Route::get('/abc', [AnalysisController::class, 'abcAnalysis']);
            Route::get('/target-stock', [AnalysisController::class, 'targetStockAnalysis']);
            // Route::get('/bcg', [AnalysisController::class, 'bcgAnalysis']);

            // Análise BCG com configuração hierárquica
            Route::post('/bcg', [AnalysisControllerUpdated::class, 'bcgAnalysis'])
                ->name('analysis.bcg');

            // Obter configurações válidas para BCG
            Route::get('/bcg/configurations', [AnalysisControllerUpdated::class, 'bcgConfigurations'])
                ->name('analysis.bcg.configurations');

            // Validar uma configuração específica
            Route::post('/bcg/validate', [AnalysisControllerUpdated::class, 'validateBCGConfiguration'])
                ->name('analysis.bcg.validate');

            // Análises existentes (mantidas para compatibilidade)
            Route::post('/abc', [AnalysisControllerUpdated::class, 'abcAnalysis'])
                ->name('analysis.abc');

            Route::post('/target-stock', [AnalysisControllerUpdated::class, 'targetStockAnalysis'])
                ->name('analysis.target-stock');
        });

        // Rotas do Motor de Planograma Automático
        Route::prefix('auto-planogram')->name('auto-planogram.')->group(function () {
            // Calcular scores automáticos para uma gôndola
            Route::post('/calculate-scores', [AutoPlanogramController::class, 'calculateScores'])
                ->name('calculate-scores');
            
            // Aplicar scores calculados aos segmentos
            Route::post('/apply-scores', [AutoPlanogramController::class, 'applyScores'])
                ->name('apply-scores');
            
            // Geração inteligente com ABC + Target Stock
            Route::post('/generate-intelligent', [AutoPlanogramController::class, 'generateIntelligent'])
                ->name('generate-intelligent');
            
            // Obter configurações do motor automático
            Route::get('/config', [AutoPlanogramController::class, 'getConfig'])
                ->name('config');
        });
    });
//Plannerate