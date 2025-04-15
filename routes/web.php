<?php


/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

use Callcocam\Plannerate\Facades\Plannerate;
use Illuminate\Support\Facades\Route;

use Callcocam\Plannerate\Http\Controllers\PlannerateController;

Route::prefix(Plannerate::getPath())
    ->middleware([
        'web',
        'auth',
    ])->group(function () {

        Route::get("/", [PlannerateController::class, 'index'])
            ->name(sprintf('%s.index', Plannerate::getRoute()))
            ->middleware('can:plannerate.index');
        Route::get("/create", [PlannerateController::class, 'create'])
            ->name(sprintf('%s.create', Plannerate::getRoute()))
            ->middleware('can:plannerate.create');
        Route::get("/edit/{planogram}", [PlannerateController::class, 'edit'])
            ->name(sprintf('%s.edit', Plannerate::getRoute()))
            ->middleware('can:plannerate.edit');
        Route::get("/show/{planogram}", [PlannerateController::class, 'show'])
            ->name(sprintf('%s.show', Plannerate::getRoute()))
            ->middleware('can:plannerate.show');

        Route::post("/store", [PlannerateController::class, 'store'])
            ->name(sprintf('%s.store', Plannerate::getRoute()))
            ->middleware('can:plannerate.store');
            
        Route::post("/update/{planogram}", [PlannerateController::class, 'update'])
            ->name(sprintf('%s.update', Plannerate::getRoute()))
            ->middleware('can:plannerate.update');


        Route::get("/show/{id}/gondola/{gondolaId}", [PlannerateController::class, 'show'])
            ->name(sprintf('%s.gondola.show', Plannerate::getRoute()))
            ->middleware('can:plannerate.gondola.show');

        Route::get("/destroy/{planogram}", [PlannerateController::class, 'destroy'])
            ->name(sprintf('%s.destroy', Plannerate::getRoute()))
            ->middleware('can:plannerate.destroy');

        Route::get("/show/{id?}/{vue_capture?}", [PlannerateController::class, 'show'])
            ->where('vue_capture', '[\/\w\.\,\-]*')
            ->name(Plannerate::getRoute());
    });
