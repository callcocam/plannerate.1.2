<?php

use Callcocam\Plannerate\Http\Controllers\Api\GondolaController;
use Callcocam\Plannerate\Http\Controllers\Api\LayerController;
use Callcocam\Plannerate\Http\Controllers\Api\PlannerateController;
use Callcocam\Plannerate\Http\Controllers\Api\SectionController;
use Callcocam\Plannerate\Http\Controllers\Api\SegmentController;
use Callcocam\Plannerate\Http\Controllers\Api\ShelfController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api')
    ->name('api.')
    ->group(function () {

        Route::get('plannerate/{id}', [PlannerateController::class, 'show'])->name('plannerate.show');
        Route::post('plannerate', [PlannerateController::class, 'save'])->name('plannerate.save'); 

        Route::resource('gondolas', GondolaController::class);
        Route::post('gondolas/{gondola}/sections/reorder', [GondolaController::class, 'reorder'])
            ->name('gondolas.sections.reorder');

        Route::post('gondolas/{gondola}/scaleFactor', [GondolaController::class, 'scaleFactor'])
            ->name('gondolas.scaleFactor');

        Route::post('gondolas/{gondola}/alignment', [GondolaController::class, 'alignment'])
            ->name('gondolas.alignment');

        Route::resource('sections', SectionController::class);

        Route::post('sections/{gondola}/shelves/reorder', [SectionController::class, 'updateInvertOrder'])
            ->name('sections.updateInvertOrder');

        Route::post('sections/{section}/alignment', [SectionController::class, 'alignment'])
            ->name('sections.alignment');

        Route::post('sections/{section}/inverterShelves', [SectionController::class, 'inverterShelves'])
            ->name('sections.invert');

        Route::resource('shelves', ShelfController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);

        // Rota para excluir prateleira pelo GondolaController
        Route::delete('gondolas/shelves/{id}', [GondolaController::class, 'destroyShelf'])
            ->name('gondolas.shelves.destroy');





        Route::resource('segments', SegmentController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);

        Route::put('segments/{shelf}/reorder', [SegmentController::class, 'reorder'])
            ->name('segments.reorder');

        Route::put('segments/{segment}/transfer', [SegmentController::class, 'transfer'])
            ->name('segments.transfer');

        Route::post('shelves/{shelf}/segments', [ShelfController::class, 'segment'])
            ->name('shelves.segments');

        Route::post('shelves/{shelf}/segments/copy', [ShelfController::class, 'segmentCopy'])
            ->name('shelves.segments.copy');

        Route::patch('shelves/{shelf}/transfer', [ShelfController::class, 'transfer'])
            ->name('shelves.transfer');


        Route::post('shelves/{shelf}/alignment', [ShelfController::class, 'alignment'])
            ->name('shelves.alignment');

        Route::post('shelves/{shelf}/inverterSegments', [ShelfController::class, 'inverterSegments'])
            ->name('shelves.invert');

        Route::post('shelves/{shelf}/updatePosition', [ShelfController::class, 'updatePosition'])
            ->name('shelves.updatePosition');


        Route::resource('layers',  LayerController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);
    });
