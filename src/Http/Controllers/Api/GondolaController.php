<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Http\Requests\Gondola\StoreGondolaRequest;
use Callcocam\Plannerate\Http\Requests\Gondola\UpdateGondolaRequest;
use Callcocam\Plannerate\Http\Resources\GondolaResource;
use Callcocam\Plannerate\Models\Gondola;
use Callcocam\Plannerate\Models\Planogram;
use Callcocam\Plannerate\Models\Shelf;
use Callcocam\Plannerate\Services\ShelfPositioningService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GondolaController extends Controller
{
    /**
     * Exibe a listagem das gôndolas de um planograma
     *
     * @param string $planogramId
     * @return AnonymousResourceCollection|JsonResponse
     */
    public function index(string $planogramId)
    {
        try {
            // Verificar se o planograma existe
            $planogram = Planogram::findOrFail($planogramId);

            $query = Gondola::query()
                ->where('planogram_id', $planogramId)
                ->latest();

            // Aplicar filtros
            if (request()->has('search')) {
                $search = request()->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            }

            if (request()->has('status')) {
                $query->where('status', request()->input('status'));
            }

            if (request()->has('side')) {
                $query->where('side', request()->input('side'));
            }

            if (request()->has('flow')) {
                $query->where('flow', request()->input('flow'));
            }

            $perPage = request()->input('per_page', 15);
            $data = $query->paginate($perPage);

            return GondolaResource::collection($data)
                ->additional([
                    'meta' => [
                        'planogram' => [
                            'id' => $planogram->id,
                            'name' => $planogram->name,
                        ],
                        'pagination' => [
                            'total' => $data->total(),
                            'count' => $data->count(),
                            'per_page' => $data->perPage(),
                            'current_page' => $data->currentPage(),
                            'total_pages' => $data->lastPage(),
                            'has_more_pages' => $data->hasMorePages(),
                            'next_page_url' => $data->nextPageUrl(),
                            'previous_page_url' => $data->previousPageUrl(),
                            'from' => $data->firstItem(),
                            'to' => $data->lastItem(),
                        ],
                    ],
                    'message' => null,
                    'status' => 'success',
                ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Planograma não encontrado',
                'status' => 'error'
            ], 404);
        } catch (Throwable $e) {
            Log::error('Erro ao listar gôndolas', [
                'planogram_id' => $planogramId,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Ocorreu um erro ao carregar as gôndolas',
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Exibe uma gôndola específica
     * 
     * @param string $id
     * @return GondolaResource|JsonResponse
     */
    public function show(string $id)
    {
        try {
            // Verificar se o planograma existe 

            $gondola = Gondola::with([
                'sections',
                'sections.shelves',
                'sections.shelves.segments',
                'sections.shelves.segments.layer',
                'sections.shelves.segments.layer.product',
                'sections.shelves.segments.layer.product.image'
            ])
                ->findOrFail($id);

            return (new GondolaResource($gondola))
                ->additional([
                    'message' => null,
                    'status' => 'success'
                ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Gôndola ou planograma não encontrado',
                'status' => 'error'
            ], 404);
        } catch (Throwable $e) {
            Log::error('Erro ao exibir gôndola', [
                'gondola_id' => $id,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Ocorreu um erro ao carregar a gôndola',
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Armazena uma nova gôndola
     *
     * @param StoreGondolaRequest $request
     * @return GondolaResource|JsonResponse
     */
    public function store(StoreGondolaRequest $request)
    {
        try {
            DB::beginTransaction();
            $planogram = Planogram::findOrFail($request->input('planogram_id'));
            $planogram->gondolas->map(function ($gondola) use ($request) {
                // Atualizar gôndola
                $gondola->sections->map(function ($section) use ($request) {
                    // Atualizar seção
                    $section->shelves->map(function ($shelf) use ($request) {
                        // Atualizar prateleira
                        $shelf->forceDelete();
                    });
                    $section->forceDelete();
                });
                $gondola->forceDelete();
            });

            // Validar dados
            $validatedData = $request->validated();

            // Adicionar informações complementares 
            $validatedData['user_id'] = auth()->id();

            // Criar seções se fornecidas
            $shelfService =  new ShelfPositioningService();
            // Obtém os dados da seção, se disponíveis
            $sectionData = $request->has('section') ? $request->input('section') : [];

            // Dados da gôndola
            $gondolaData = [
                'planogram_id' => $request->input('planogram_id'),
                'name' => $request->input('name'),
                'location' => $request->input('location'),
                'side' => $request->input('side', null),
                'flow' => $request->input('flow', 'left_to_right'),
                'scale_factor' => $request->input('scale_factor', 3),
                'num_modulos' => isset($sectionData['num_modulos']) ? (int)$sectionData['num_modulos'] : 1,
                'status' => $request->input('status', 'draft'),
                'user_id' => auth()->id(),
                'tenant_id' => $planogram->tenant_id,
            ];
            // Criar a gôndola
            $gondola = Gondola::create($gondolaData);

            // Criar seção se fornecida
            // Se temos dados para criar seções
            if ($request->has('section')) {
                $sectionData = $request->input('section');
                $num_modulos = isset($sectionData['num_modulos']) ? (int)$sectionData['num_modulos'] : 1;
                for ($num = 0; $num < $num_modulos; $num++) {
                    // Nome da seção
                    $sectionName = $num . '# ' . ($sectionData['name'] ?? 'Seção');
                    if ($sectionData['name'] == 'undefined - Seção') {
                        $sectionName = $num . '# Seção';
                    }

                    $sectionSettings = $sectionData['settings'] ?? [];
                    $sectionSettings['holes'] = $shelfService->calculateHoles($sectionData, $gondola->scale_factor);

                    // Preparar dados da seção
                    $sectionToCreate = [
                        'gondola_id' => $gondola->id,
                        'name' => $sectionName,
                        'code' => 'S' . now()->format('ymd') . rand(1000, 9999),
                        'width' => data_get($sectionData, 'width', 130),
                        'height' => data_get($sectionData, 'height', 180),
                        'num_shelves' =>  data_get($sectionData, 'num_shelves', 4),
                        'base_height' => data_get($sectionData, 'base_height', 17),
                        'base_depth' => data_get($sectionData, 'base_depth', 40),
                        'base_width' => data_get($sectionData, 'base_width', 17),
                        'cremalheira_width' => data_get($sectionData, 'cremalheira_width', 4),
                        'hole_height' => data_get($sectionData, 'hole_height', 2),
                        'hole_width' => data_get($sectionData, 'hole_width', 2),
                        'hole_spacing' => data_get($sectionData, 'hole_spacing', 2),
                        'ordering' => $num,
                        'settings' =>  $sectionSettings,
                        'status' => $request->input('status', 'draft'),
                        'user_id' => auth()->id(),
                        'tenant_id' => $planogram->tenant_id,
                    ];

                    // Criar a seção
                    $section = $gondola->sections()->create($sectionToCreate);

                    // Definir a quantidade de prateleiras
                    $shelfQty = data_get($sectionData, 'shelf_config.num_shelves', 4);
                    $product_type = data_get($sectionData, 'shelf_config.product_type', 'normal');

                    // Criar prateleiras
                    for ($i = 0; $i < $shelfQty; $i++) {
                        // Calcular posição vertical da prateleira (shelf_position)
                        $position = $shelfService->calculateShelfPosition($shelfQty, data_get($sectionData, 'shelf_config.shelf_height', 4), data_get($sectionSettings, 'holes', []), $i, $gondola->scale_factor);

                        $shelfData = [
                            'section_id' => $section->id,
                            'code' => 'SLF' . $i . '-' . now()->format('ymd') . rand(100, 999),
                            'product_type' => $product_type,
                            'shelf_width' => data_get($sectionData, 'shelf_config.shelf_width', 130),
                            'shelf_height' => data_get($sectionData, 'shelf_config.shelf_height', 4),
                            'shelf_depth' => data_get($sectionData, 'shelf_config.shelf_depth', 40),
                            'shelf_position' => round($position),
                            'ordering' => $i,
                            'settings' => [],
                            'status' => $request->input('status', 'draft'),
                            'user_id' => auth()->id(),
                            'tenant_id' => $planogram->tenant_id,
                        ];

                        $section->shelves()->create($shelfData);
                    }
                }
            }
            DB::commit();

            // Carregar relacionamentos para o retorno
            $gondola = $gondola->fresh(['sections', 'sections.shelves']);

            return (new GondolaResource($gondola))
                ->additional([
                    'message' => 'Gôndola criada com sucesso',
                    'status' => 'success'
                ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Planograma não encontrado',
                'status' => 'error'
            ], 404);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Erro ao criar gôndola', [
                'data' => $request->all(),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Ocorreu um erro ao criar a gôndola',
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Atualiza uma gôndola existente
     *
     * @param UpdateGondolaRequest $request
     * @param string $planogramId
     * @param string $id
     * @return GondolaResource|JsonResponse
     */
    public function update(UpdateGondolaRequest $request, string $planogramId, string $id)
    {
        try {
            DB::beginTransaction();

            // Verificar se o planograma existe
            Planogram::findOrFail($planogramId);

            // Buscar a gôndola
            $gondola = Gondola::where('planogram_id', $planogramId)->findOrFail($id);

            // Validar dados
            $validatedData = $request->validated();

            // Atualizar slug se o nome foi alterado
            if (isset($validatedData['name']) && $gondola->name !== $validatedData['name']) {
                $validatedData['slug'] = Str::slug($validatedData['name']);
            }

            // Atualizar a gôndola
            $gondola->update($validatedData);

            // Atualizar seção se fornecida
            if ($request->has('section')) {
                $sectionData = $request->input('section');

                // Verificar se a seção existe
                if ($gondola->sections()->exists()) {
                    $section = $gondola->sections()->first();
                    $section->update($sectionData);
                } else {
                    // Criar seção se não existir
                    $sectionData['gondola_id'] = $gondola->id;
                    $sectionData['user_id'] = auth()->id();
                    $sectionData['tenant_id'] = auth()->user()->tenant_id ?? null;

                    $section = $gondola->sections()->create($sectionData);
                }

                // Atualizar prateleiras se necessário
                if (isset($sectionData['num_shelves'])) {
                    // Obter o número atual de prateleiras
                    $currentShelves = $section->shelves()->count();
                    $numShelves = $sectionData['num_shelves'];

                    if ($numShelves > $currentShelves) {
                        // Adicionar novas prateleiras
                        $shelves = [];
                        for ($i = $currentShelves; $i < $numShelves; $i++) {
                            $shelves[] = [
                                'id' => (string) Str::ulid(),
                                'section_id' => $section->id,
                                'name' => "Prateleira " . ($i + 1),
                                'position' => $i,
                                'height' => $sectionData['shelf_height'] ?? 4,
                                'width' => $sectionData['width'] ?? 130,
                                'depth' => $sectionData['shelf_depth'] ?? 40,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        if (!empty($shelves)) {
                            $section->shelves()->insert($shelves);
                        }
                    } elseif ($numShelves < $currentShelves) {
                        // Remover prateleiras excedentes
                        $section->shelves()->where('position', '>=', $numShelves)->delete();
                    }

                    // Atualizar dimensões das prateleiras existentes
                    if (isset($sectionData['shelf_height']) || isset($sectionData['width']) || isset($sectionData['shelf_depth'])) {
                        $section->shelves()->update([
                            'height' => $sectionData['shelf_height'] ?? $section->shelf_height ?? 4,
                            'width' => $sectionData['width'] ?? $section->width ?? 130,
                            'depth' => $sectionData['shelf_depth'] ?? $section->shelf_depth ?? 40,
                        ]);
                    }
                }
            }

            DB::commit();

            // Carregar relacionamentos para o retorno
            $gondola = $gondola->fresh(['sections', 'sections.shelves']);

            return (new GondolaResource($gondola))
                ->additional([
                    'message' => 'Gôndola atualizada com sucesso',
                    'status' => 'success'
                ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gôndola ou planograma não encontrado',
                'status' => 'error'
            ], 404);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Erro ao atualizar gôndola', [
                'planogram_id' => $planogramId,
                'gondola_id' => $id,
                'data' => $request->all(),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Ocorreu um erro ao atualizar a gôndola',
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Remove uma gôndola
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(Gondola $gondola)
    {
        try {
            DB::beginTransaction();
            $gondola->sections->map(function ($section) {
                // Atualizar seção
                $section->shelves->map(function ($shelf) {

                    $shelf->segments->map(function ($segment) {
                        $segment->layer()->forceDelete();
                        // Atualizar segmento
                        $segment->forceDelete();
                    });
                    // Atualizar prateleira
                    $shelf->forceDelete();
                });
                $section->forceDelete();
            });
            $gondola->forceDelete();

            DB::commit();

            return response()->json([
                'message' => 'Gôndola excluída com sucesso',
                'status' => 'success'
            ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gôndola ou planograma não encontrado',
                'status' => 'error'
            ], 404);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Erro ao excluir gôndola', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Ocorreu um erro ao excluir a gôndola',
                'status' => 'error'
            ], 500);
        }
    }

    public function reorder(Request $request, Gondola $gondola): JsonResponse
    {
        try {
            $sections = $request->input('sectionIds');

            foreach ($sections as $order =>  $section) {
                $sectionModel = $gondola->sections()->findOrFail($section);
                $sectionModel->update(['ordering' => $order]);
            }

            return response()->json([
                'message' => 'Gôndola reordenada com sucesso',
                'status' => 'success'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Gôndola ou seção não encontrada',
                'status' => 'error'
            ], 404);
        } catch (Throwable $e) {
            Log::error('Erro ao reordenar gôndola', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Ocorreu um erro ao reordenar a gôndola',
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Remove uma prateleira (shelf) específica
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroyShelf(string $id)
    {
        try {
            DB::beginTransaction();
            
            // Buscar a prateleira pelo ID
            $shelf = Shelf::with(['segments', 'segments.layer'])->findOrFail($id);
            
            // Excluir segmentos e layers associados à prateleira
            if ($shelf->segments) {
                foreach ($shelf->segments as $segment) {
                    // Excluir layer do segmento
                    if ($segment->layer) {
                        $segment->layer->forceDelete();
                    }
                    // Excluir segmento
                    $segment->forceDelete();
                }
            }
            
            // Excluir a prateleira
            $shelf->forceDelete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Prateleira excluída com sucesso',
                'status' => 'success'
            ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Prateleira não encontrada',
                'status' => 'error'
            ], 404);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Erro ao excluir prateleira', [
                'shelf_id' => $id,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'message' => 'Ocorreu um erro ao excluir a prateleira',
                'status' => 'error'
            ], 500);
        }
    }

}
