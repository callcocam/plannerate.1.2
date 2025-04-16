<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Http\Requests\Shelf\StoreShelfRequest;
use Callcocam\Plannerate\Http\Resources\ShelfResource;
use Callcocam\Plannerate\Models\Shelf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShelfController extends Controller
{


    /**
     * @param Request $request
     * @param Shelf $shelf
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Shelf $shelf)
    {
        $shelf->load([
            'segments',
            'segments.layer',
            'segments.layer.product',
            'segments.layer.product.image',
        ]);
        return response()->json([
            'message' => 'Prateleiras carregadas com sucesso',
            'data' => ShelfResource::collection($shelf->all()),
        ], 200);
    }

    /**
     * @param Request $request
     * @param Shelf $shelf
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Shelf $shelf)
    {
        $shelf->load([
            'segments',
            'segments.layer',
            'segments.layer.product',
            'segments.layer.product.image',
        ]);
        return response()->json([
            'message' => 'Prateleira carregada com sucesso',
            'data' => new ShelfResource($shelf),
        ], 200);
    }

    /**
     * @param Request $request
     * @param Shelf $shelf
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Shelf $shelf)
    {
        $validated = $request->all();

        // Processa atualização normal
        try {
            DB::beginTransaction();
            $shelf = $shelf->create($validated);
            DB::commit();
            return response()->json([
                'message' => 'Prateleira criada com sucesso',
                'data' => new ShelfResource($shelf),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao criar prateleira: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param Request $request
     * @param Shelf $shelf
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Shelf $shelf)
    {
        $validated = $request->all();

        // Processa atualização normal
        try {
            DB::beginTransaction();
            $segments = data_get($validated, 'segments');
            if ($segments) {
                foreach ($segments as $segment) {
                    $shelf->segments()->updateOrCreate(
                        ['id' => data_get($segment, 'id')],
                        $segment
                    );
                }
            }
            $shelf->update($validated);
            $shelf->load([
                'segments',
                'segments.layer',
                'segments.layer.product',
                'segments.layer.product.image',
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Prateleira atualizada com sucesso',
                'data' => new ShelfResource($shelf),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao atualizar prateleira: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * @param Request $request
     * @param Shelf $shelf
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Shelf $shelf)
    {
        try {
            DB::beginTransaction();
            $shelf->delete();
            DB::commit();
            return response()->json([
                'message' => 'Prateleira excluída com sucesso',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao excluir prateleira: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function segment(StoreShelfRequest $request, Shelf $shelf,)
    {
        $validated = $request->validated();

        // Processa atualização normal com possível adição de segmento/camada
        $segment = data_get($validated, 'segment');
        $layer = data_get($segment, 'layer');
        try {
            if ($segment) {
                DB::beginTransaction();
                $newSegment =  $shelf->segments()->create($segment);
                if ($newSegment) {
                    $newSegment->layer()->create($layer);
                }
                DB::commit();
            }
            $shelf->load([
                'segments',
                'segments.layer',
                'segments.layer.product',
                'segments.layer.product.image',
            ]);
            return response()->json([
                'message' => 'Segmento criado com sucesso',
                'data' => new ShelfResource($shelf),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao criar segmento: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function segmentCopy(StoreShelfRequest $request, Shelf $shelf)
    {
        $validated = $request->validated();

        // Processa atualização normal com possível adição de segmento/camada
        $segment = data_get($validated, 'segment');
        $layer = data_get($segment, 'layer');
        try {
            if ($segment) {
                DB::beginTransaction();
                $newSegment =  $shelf->segments()->create($segment);
                if ($newSegment) {
                    $newSegment->layer()->create($layer);
                }
                DB::commit();
            }
            $shelf->load([
                'segments',
                'segments.layer',
                'segments.layer.product',
                'segments.layer.product.image',
            ]);
            return response()->json([
                'message' => 'Segmento copiado com sucesso',
                'data' => new ShelfResource($shelf),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao copiar segmento: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function transfer(Request $request, Shelf $shelf)
    {
        $validated = $request->all();
        $shelf->update($validated);
        return response()->json([
            'message' => 'Prateleira transferida com sucesso',
            'data' => new ShelfResource($shelf),
        ], 200);
    }
}
