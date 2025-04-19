<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller; 
use Callcocam\Plannerate\Http\Resources\PlannerateResource;
use Callcocam\Plannerate\Models\Planogram;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse; 
use Illuminate\Support\Facades\Log;
use Throwable;

class PlannerateController extends Controller
{
    
    /**
     * Exibe um planograma específico
     * 
     * @param Planogram $planogram
     * @return PlannerateResource|JsonResponse
     */
    public function show(string $id)
    {
        try {
            $planogram = $this->getModel()::query(0)->with(['tenant','store', 'cluster', 'department', 
            'gondolas',
            'gondolas.sections',
            'gondolas.sections.shelves',
            'gondolas.sections.shelves.segments',
            'gondolas.sections.shelves.segments.layer',
            'gondolas.sections.shelves.segments.layer.product'
            ])->findOrFail($id);
 

            return new PlannerateResource($planogram);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Planograma não encontrado',
                'status' => 'error'
            ], 404);
        } catch (Throwable $e) {
            Log::error('Erro ao exibir planograma', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
 

    protected function getModel()
    {
        if (class_exists('App\Models\Planogram')) {
            return 'App\Models\Planogram';
        }
        return Planogram::class;
    }
}
