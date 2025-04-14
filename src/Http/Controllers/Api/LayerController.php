<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Http\Requests\Layer\Api\UpdateLayerRequest;
use Callcocam\Plannerate\Http\Resources\LayerResource;
use Callcocam\Plannerate\Models\Layer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LayerController extends Controller
{
    /**
     * @param Request $request
     * @param Layer $layer
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Layer $layer)
    {
        try {
            DB::beginTransaction();
            $layer->load([
                'product',
                'product.image',
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Camadas carregadas com sucesso',
                'data' => LayerResource::collection($layer->all()),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao carregar as camadas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * @param Request $request
     * @param Layer $layer
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Layer $layer)
    {
        try {
            DB::beginTransaction();
            $layer->load([
                'product',
                'product.image',
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Camada carregada com sucesso',
                'data' => new LayerResource($layer),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao carregar a camada',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * @param Request $request
     * @param Layer $layer
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Layer $layer)
    {
        $validated = $request->all();

        // Processa atualização normal
        try {
            DB::beginTransaction();
            $layer = $layer->create($validated);
            DB::commit();
            return response()->json([
                'message' => 'Camada criada com sucesso',
                'data' => new LayerResource($layer),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao criar camada: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * @param Request $request
     * @param Layer $layer
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateLayerRequest $request, Layer $layer)
    {
        $validated = $request->validated();

        // Processa atualização normal
        try {
            DB::beginTransaction();
            $layer->update($validated);
            DB::commit();
            return response()->json([
                'message' => 'Camada atualizada com sucesso',
                'data' => new LayerResource($layer),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao atualizar camada: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * @param Request $request
     * @param Layer $layer
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Layer $layer)
    {
        // Processa exclusão normal
        try {
            DB::beginTransaction();
            $layer->delete();
            // Remove o segmento da camada
            $layer->segment()->delete();
            DB::commit();
            return response()->json([
                'message' => 'Camada excluída com sucesso',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao excluir camada: ' . $e->getMessage(),
            ], 500);
        }
    }
}
