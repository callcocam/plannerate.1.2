<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Facades\Plannerate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlannerateController extends Controller
{


    public function index()
    {

        return Inertia::render('plannerate/Index', [
            'title' => 'Planejamento de Tarefas',
            'description' => 'Planejamento de Tarefas',
            'breadcrumbs' => [
                ['title' => 'Planejamento de Tarefas', 'url' => route(Plannerate::getRoute())],
            ],
        ]);
    }

    public function gondola()
    {
        return Inertia::render('plannerate/Gondola', [
            'title' => 'Gôndola',
            'description' => 'Gôndola',
            'breadcrumbs' => [
                ['title' => 'Planejamento de Tarefas', 'url' => route(Plannerate::getRoute())],
                ['title' => 'Gôndola', 'url' => route(Plannerate::getRoute() . '.gondola')],
            ],
        ]);
    }
}
