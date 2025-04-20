<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use Callcocam\Plannerate\Http\Requests\Segment\Api\UpdateTransferSegmentRequest;
use Callcocam\Plannerate\Http\Resources\SegmentResource;
use Callcocam\Plannerate\Http\Resources\ShelfResource;
use Callcocam\Plannerate\Models\Segment;
use Callcocam\Plannerate\Models\Shelf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SegmentController extends Controller
{
    /**
     * @param Request $request
     * @param Segment $segment
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Segment  $segment)
    {
        $segment->load([
            'layer',
            'layer.product',
            'layer.product.image',
        ]);
        return $this->handleSuccess('Segmentos carregados com sucesso', [
            'data' => SegmentResource::collection($segment->all()),
        ]);
    }

    /** 
     * @param Segment $segment
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Segment $segment)
    {
        try {
            DB::beginTransaction();
            $segment->load([
                'layer',
                'layer.product',
                'layer.product.image',
            ]);
            return $this->handleSuccess('Segmento carregado com sucesso', [
                'data' => new SegmentResource($segment),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao carregar segmento');
        } finally {
            DB::commit();
        }
    }
    /**
     * @param Request $request
     * @param Segment $segment
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Segment $segment)
    {
        try {
            DB::beginTransaction();
            $validated = $request->all();
            $segment = Segment::create($validated);
            return $this->handleSuccess('Segmento criado com sucesso', [
                'data' => new SegmentResource($segment),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao criar segmento');
        } finally {
            DB::commit();
        }
    }
    /**
     * @param Request $request
     * @param Segment $segment
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Segment $segment)
    {
        try {
            DB::beginTransaction();
            $validated = $request->all();
            $segment->update($validated);
            return $this->handleSuccess('Segmento atualizado com sucesso', [
                'data' => new SegmentResource($segment),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao atualizar segmento');
        } finally {
            DB::commit();
        }
    }
    /**
     * @param Segment $segment
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Segment $segment)
    {
        try {
            DB::beginTransaction();
            $segment->delete();
            return $this->handleSuccess('Segmento deletado com sucesso');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao deletar segmento');
        } finally {
            DB::commit();
        }
    }
}
