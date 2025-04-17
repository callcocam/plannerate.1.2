<?php

namespace Callcocam\Plannerate\Http\Controllers;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Facades\Plannerate;
use Callcocam\Plannerate\Models\Planogram;
use Illuminate\Http\Request;

class AppController extends Controller
{
    public function show(Request $request)
    {
        // Busca o planograma pelo ID com seus relacionamentos 

        return view('plannerate::app', [ 
            'title' => 'Visualizar Planograma',
            'description' => 'Detalhes do planograma',
            'breadcrumbs' => [
                ['title' => 'Planogramas', 'url' => route(Plannerate::getRoute())], 
            ],
        ]);
    }
}
