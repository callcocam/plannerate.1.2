<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api; 

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
            return $this->handleSuccess('Camadas carregadas com sucesso', [
                'data' => LayerResource::collection($layer->all()),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao carregar as camadas');
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
            $layer->load([
                'product',
                'product.image',
                'product.sales',
                'product.purchases',
            ]); 
            return response()->json(new LayerResource($layer), 200);
        } catch (\Exception $e) { 
            return $this->handleInternalServerError('Erro ao carregar a camada');
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
            return $this->handleSuccess('Camada criada com sucesso', [
                'data' => new LayerResource($layer),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao criar camada: ' . $e->getMessage());
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
            return $this->handleSuccess('Camada atualizada com sucesso', [
                'data' => new LayerResource($layer),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao atualizar camada: ' . $e->getMessage());
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
            return $this->handleSuccess('Camada excluída com sucesso');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao excluir camada: ' . $e->getMessage());
        }
    }
}
