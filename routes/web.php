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
        Route::get("/{vue_capture?}", [PlannerateController::class, 'index'])
            ->where('vue_capture', '[\/\w\.\,\-]*')
            ->name(Plannerate::getRoute());
    });
