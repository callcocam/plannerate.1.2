<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

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
            'segments.layer.product.sales',
            'segments.layer.product.purchases',
        ]);
        return $this->handleSuccess('Prateleiras carregadas com sucesso', [
            'data' => ShelfResource::collection($shelf->all()),
        ]);
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
            'segments.layer.product.sales',
            'segments.layer.product.purchases',
        ]);
        return $this->handleSuccess('Prateleira carregada com sucesso', [
            'data' => new ShelfResource($shelf),
        ]);
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
            return $this->handleSuccess('Prateleira criada com sucesso', [
                'data' => new ShelfResource($shelf),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao criar prateleira: ' . $e->getMessage());
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
                'segments.layer.product.sales',
                'segments.layer.product.purchases',
            ]);
            DB::commit();
            return $this->handleSuccess('Prateleira atualizada com sucesso', [
                'data' => new ShelfResource($shelf),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao atualizar prateleira: ' . $e->getMessage());
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
            return $this->handleSuccess('Prateleira excluída com sucesso');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao excluir prateleira: ' . $e->getMessage());
        }
    }

    public function segment(StoreShelfRequest $request, Shelf $shelf)
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
                'segments.layer.product.sales',
                'segments.layer.product.purchases',
            ]);
            $newSegment->load([
                'layer',
                'layer.product',
                'layer.product.image',
            ]);
            return $this->handleSuccess('Segmento criado com sucesso', [
                'data' => [
                    'shelf' => new ShelfResource($shelf),
                    'segment' => $newSegment ? new \Callcocam\Plannerate\Http\Resources\SegmentResource($newSegment) : null,
                ],

            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao criar segmento: ' . $e->getMessage());
        }
    }


    /**
     * @param Request $request
     * @param Shelf $shelf
     * @return \Illuminate\Http\JsonResponse
     */
    public function shelfCopy(Shelf $shelf)
    {
        $validated = $shelf->toArray();

        // Processa atualização normal
        try {
            DB::beginTransaction();
            $shelfCopy = Shelf::create(array_merge($validated, [
                'code' => uniqid('shelf-'),
                'name' => $shelf->name . ' (Cópia)',
                'shelf_position' => $shelf->shelf_position + 1,
            ]));
            if ($shelfCopy) {
                foreach ($shelf->segments as $segment) {
                    $newSegment =  $shelfCopy->segments()->create($segment->toArray());
                    if ($newSegment && $segment->layer) {
                        $newSegment->layer()->create($segment->layer->toArray());
                    }
                }
            }
            $shelfCopy->load([
                'segments',
                'segments.layer',
                'segments.layer.product',
            ]);
            DB::commit();
            return $this->handleSuccess('Prateleira criada com sucesso', [
                'data' => new ShelfResource($shelfCopy),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao criar prateleira: ' . $e->getMessage());
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
                'segments.layer.product.sales',
                'segments.layer.product.purchases',
            ]);
            return $this->handleSuccess('Segmento copiado com sucesso', [
                'data' => new ShelfResource($shelf),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Erro ao copiar segmento: ' . $e->getMessage());
        }
    }

    public function transfer(Request $request, Shelf $shelf)
    {
        $validated = $request->all();
        $shelf->update($validated);
        return $this->handleSuccess('Prateleira transferida com sucesso', [
            'data' => new ShelfResource($shelf),
        ]);
    }

    public function inverterSegments(Request $request, Shelf $shelf)
    {
        $validated = $request->all();
        $shelf->update($validated);
        return $this->handleSuccess('Segmentos invertidos com sucesso', [
            'data' => new ShelfResource($shelf),
        ]);
    }
    public function alignment(Request $request, Shelf $shelf)
    {
        $validated = $request->validate([
            'alignment' => 'required|string|max:255',
        ]);
        $shelf->update($validated);
        return $this->handleSuccess('Alinhamento atualizado com sucesso', [
            'data' => new ShelfResource($shelf),
        ]);
    }

    public function updatePosition(Request $request, Shelf $shelf)
    {
        $validated = $request->validate([
            'shelf_position' => 'required|integer',
        ]);
        $shelf->update($validated);
        return $this->handleSuccess('Posição atualizada com sucesso', [
            'data' => new ShelfResource($shelf),
        ]);
    }
}
