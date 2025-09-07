<?php


/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

use Callcocam\Plannerate\Facades\Plannerate;
use Callcocam\Plannerate\Http\Controllers\AppController;
use Illuminate\Support\Facades\Route;

// Rotas públicas para QR Code (sem autenticação)
Route::prefix(Plannerate::getPath())
    ->middleware(['web'])
    ->group(function () {

        Route::get("/qr/{id?}/gondola/{gondolaId?}/{vue_capture?}", [AppController::class, 'show'])
            ->where('vue_capture', '[\/\w\.\,\-]*')
            ->name(sprintf('%s.qr.show', Plannerate::getRoute()));

        Route::get("/qr/{vue_capture?}", [AppController::class, 'show'])
            ->where('vue_capture', '[\/\w\.\,\-]*')
            ->name(sprintf('%s.qr', Plannerate::getRoute()));
    });

// Rotas protegidas (com autenticação)
Route::prefix(Plannerate::getPath())
    ->middleware([
        'web',
        'auth',
    ])->group(function () {



        Route::get("/editor/{id}/criar", [AppController::class, 'show'])
            ->name(sprintf('%s.editor.create', Plannerate::getRoute()));
            
        Route::get("/editor/{id?}/gondola/{gondolaId?}/{vue_capture?}", [AppController::class, 'show'])
            ->where('vue_capture', '[\/\w\.\,\-]*')
            ->name(sprintf('%s.editor.show', Plannerate::getRoute()));

        Route::get("/editor/{vue_capture?}", [AppController::class, 'show'])
            ->where('vue_capture', '[\/\w\.\,\-]*')
            ->name(sprintf('%s.editor', Plannerate::getRoute()));
    });
