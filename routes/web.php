<?php


/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

use Callcocam\Plannerate\Facades\Plannerate;
use Callcocam\Plannerate\Http\Controllers\AppController;
use Illuminate\Support\Facades\Route;

Route::prefix(Plannerate::getPath())
    ->middleware([
        'web',
        'auth',
    ])->group(function () {

        Route::get("/editor/{vue_capture?}", [AppController::class, 'show'])
            ->where('vue_capture', '[\/\w\.\,\-]*')
            ->name(Plannerate::getRoute());
    });
