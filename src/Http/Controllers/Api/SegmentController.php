<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Http\Requests\Segment\Api\UpdateTransferSegmentRequest;
use Callcocam\Plannerate\Http\Resources\SegmentResource;
use Callcocam\Plannerate\Http\Resources\ShelfResource;
use Callcocam\Plannerate\Models\Gondola;
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
        return response()->json([
            'message' => 'Segmentos carregados com sucesso',
            'data' => SegmentResource::collection($segment->all()),
        ], 200);
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
            return response()->json([
                'message' => 'Segmento carregado com sucesso',
                'data' => new SegmentResource($segment),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao carregar segmento',
                'error' => $e->getMessage(),
            ], 500);
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
            return response()->json([
                'message' => 'Segmento criado com sucesso',
                'data' => new SegmentResource($segment),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao criar segmento',
                'error' => $e->getMessage(),
            ], 500);
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
            return response()->json([
                'message' => 'Segmento atualizado com sucesso',
                'data' => new SegmentResource($segment),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao atualizar segmento',
                'error' => $e->getMessage(),
            ], 500);
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
            return response()->json([
                'message' => 'Segmento deletado com sucesso',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao deletar segmento',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            DB::commit();
        }
    }
    /**
     * @param Request $request
     * @param Shelf $segment
     * @return \Illuminate\Http\JsonResponse
     */
    public function reorder(Request $request, Shelf $shelf)
    {
        try {
            DB::beginTransaction();
            $validated = $request->all();
            return response()->json([
                'message' => 'Segmento reordenado com sucesso',
                'data' => new ShelfResource($shelf),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao reordenar segmento',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            DB::commit();
        }
    }

    public function transfer(UpdateTransferSegmentRequest $request, Segment $segment)
    {
        try {
            DB::beginTransaction();
            $validated = $request->validated();
            $segment->update($validated);
            return response()->json([
                'message' => 'Segmento transferido com sucesso',
                'data' => new SegmentResource($segment),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao transferir segmento',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            DB::commit();
        }
    }
}
