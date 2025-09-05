<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

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
            return $this->handleNotFoundException('Planograma não encontrado');
        } catch (Throwable $e) {

            return $this->handleInternalServerError('Ocorreu um erro ao carregar as gôndolas');
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
                'sections.shelves.segments.layer.product.sales',
                'sections.shelves.segments.layer.product.image',
                'sections.shelves.section',
                'sections.shelves.section.gondola',
            ])
                ->findOrFail($id);

            return (new GondolaResource($gondola))
                ->additional([
                    'message' => null,
                    'status' => 'success'
                ]);
        } catch (ModelNotFoundException $e) {
            return $this->handleNotFoundException('Gôndola ou planograma não encontrado');
        } catch (Throwable $e) {
            return $this->handleInternalServerError('Ocorreu um erro ao carregar a gôndola');
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

            // Limpar gôndolas existentes
            // $this->deleteExistingGondolas($planogram->gondolas); 

            // Validar dados
            $validatedData = $request->validated();
            $validatedData['user_id'] = auth()->id(); 

            // Criar nova gôndola
            $gondola = $this->createGondola($request, $planogram);

            // Criar seções e prateleiras
            $this->createSectionsWithShelves($gondola, $request);

            DB::commit();

            // Carregar relacionamentos para o retorno
            $gondola = $gondola->fresh(['sections', 'sections.shelves']);

            return (new GondolaResource($gondola))
                ->additional([
                    'message' => 'Gôndola criada com sucesso',
                    'status' => 'success'
                ]);
        } catch (ModelNotFoundException $e) {
            return $this->handleNotFoundException('Planograma não encontrado');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Erro ao criar gôndola', $request->all());
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
    public function update(UpdateGondolaRequest $request, string $id)
    {
        try {
            DB::beginTransaction();

            $gondola = Gondola::findOrFail($id); 

            // Limpar seções e prateleiras existentes
            // $this->deleteSectionsAndShelves($gondola->sections);

            // Validar dados
            $validatedData = $request->validated();

            $gondola->update($validatedData);
            // Atualizar a gôndola
            // $this->updateGondola($gondola, $request);

            // Recriar seções e prateleiras
            // $this->createSectionsWithShelves($gondola, $request);

            DB::commit();

            // Carregar relacionamentos para o retorno
            $gondola = $gondola->fresh(['sections', 'sections.shelves']);

            return (new GondolaResource($gondola))
                ->additional([
                    'message' => 'Gôndola atualizada com sucesso',
                    'status' => 'success'
                ]);
        } catch (ModelNotFoundException $e) {
            return $this->handleNotFoundException('Gôndola ou planograma não encontrado');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Erro ao atualizar gôndola', [
                'planogram_id' => $gondola->planogram_id,
                'gondola_id' => $id,
                'data' => $request->all()
            ]);
        }
    }

    /**
     * Remove uma gôndola
     *
     * @param Gondola $gondola
     * @return JsonResponse
     */
    public function destroy(Gondola $gondola)
    {
        try {
            DB::beginTransaction();

            // Limpar seções e prateleiras
            $this->deleteGondolaWithRelations($gondola);

            DB::commit();

            return $this->handleSuccess('Gôndola excluída com sucesso');
        } catch (ModelNotFoundException $e) {
            return $this->handleNotFoundException('Gôndola ou planograma não encontrado');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Erro ao excluir gôndola');
        }
    }

    /**
     * Cria uma nova gôndola
     *
     * @param Request $request
     * @param Planogram $planogram
     * @return Gondola
     */
    private function createGondola(Request $request, Planogram $planogram): Gondola
    {
        $gondolaData = [
            'planogram_id' => $request->input('planogram_id', $planogram->id),
            'name' => $request->input('name', 'GND-' . now()->format('ymd') . '-' . rand(1000, 9999)),
            'location' => $request->input('location'),
            'side' => $request->input('side', 'A'),
            'flow' => $request->input('flow', 'left_to_right'),
            'scale_factor' => $request->input('scale_factor', 3),
            'num_modulos' => $request->input('num_modulos', 4),
            'status' => $request->input('status', 'published'),
            'linked_map_gondola_id' => $request->input('linked_map_gondola_id', null),
            'user_id' => auth()->id(),
            'tenant_id' => $planogram->tenant_id,
        ];

        return Gondola::create($gondolaData);
    }

    /**
     * Atualiza uma gôndola existente
     *
     * @param Gondola $gondola
     * @param Request $request
     * @return void
     */
    private function updateGondola(Gondola $gondola, Request $request): void
    {
        $gondolaData = [
            'name' => $request->input('name', $gondola->name),
            'location' => $request->input('location', $gondola->location),
            'side' => $request->input('side', $gondola->side),
            'flow' => $request->input('flow', $gondola->flow),
            'scale_factor' => $request->input('scale_factor', $gondola->scale_factor),
            'num_modulos' => $request->input('num_modulos', $gondola->num_modulos),
            'status' => $request->input('status', $gondola->status),
            'user_id' => auth()->id(),
        ];

        // Atualizar slug se o nome foi alterado
        if ($gondola->name !== $gondolaData['name']) {
            $gondolaData['slug'] = Str::slug($gondolaData['name']);
        }

        $gondola->update($gondolaData);
    }

    /**
     * Cria seções e prateleiras para uma gôndola
     *
     * @param Gondola $gondola
     * @param Request $request
     * @return void
     */
    private function createSectionsWithShelves(Gondola $gondola, Request $request): void
    {
        $shelfService = new ShelfPositioningService();
        $num_modulos = $request->input('num_modulos', $gondola->num_modulos);

        for ($num = 0; $num < $num_modulos; $num++) {
            // Criar seção
            $sectionName = $num . '# Seção';

            // Calcular furos para posicionamento das prateleiras
            $sectionSettings = [
                'holes' => $shelfService->calculateHoles([
                    'height' => $request->input('altura_secao', 180),
                    'hole_height' => $request->input('altura_furo', 3),
                    'hole_spacing' => $request->input('espacamento_furo', 2),
                    'num_shelves' => $request->input('num_prateleiras', 4),
                    'hole_width' => $request->input('largura_furo', 2),
                    'base_height' => $request->input('altura_base', 17),
                ])
            ];

            // Dados da seção
            $sectionToCreate = [
                'gondola_id' => $gondola->id,
                'name' => $sectionName,
                'code' => 'S' . now()->format('ymd') . rand(1000, 9999),
                'width' => $request->input('largura_secao', 130),
                'height' => $request->input('altura_secao', 180),
                'num_shelves' => $request->input('num_prateleiras', 4),
                'base_height' => $request->input('altura_base', 17),
                'base_depth' => $request->input('profundidade_base', 40),
                'base_width' => $request->input('largura_base', 130),
                'cremalheira_width' => $request->input('largura_cremalheira', 4),
                'hole_height' => $request->input('altura_furo', 3),
                'hole_width' => $request->input('largura_furo', 2),
                'hole_spacing' => $request->input('espacamento_furo', 2),
                'ordering' => $num,
                'settings' => $sectionSettings,
                'status' => $request->input('status', 'published'),
                'user_id' => auth()->id(),
                'tenant_id' => $gondola->tenant_id,
            ];

            // Criar a seção
            $section = $gondola->sections()->create($sectionToCreate);

            // Criar prateleiras para a seção
            $this->createShelvesForSection($section, $request, $sectionSettings, $shelfService);
        }
    }

    /**
     * Cria prateleiras para uma seção
     *
     * @param mixed $section
     * @param Request $request
     * @param array $sectionSettings
     * @param ShelfPositioningService $shelfService
     * @return void
     */
    private function createShelvesForSection($section, Request $request, array $sectionSettings, ShelfPositioningService $shelfService): void
    {
        $shelfQty = $request->input('num_prateleiras', 4);
        $product_type = $request->input('tipo_produto_prateleira', 'normal');

        for ($i = 0; $i < $shelfQty; $i++) {
            // Calcular posição vertical da prateleira
            $position = $shelfService->calculateShelfPosition(
                $shelfQty,
                $request->input('altura_prateleira', 4),
                $sectionSettings['holes'],
                $i,
                $section->gondola->scale_factor
            );

            $shelfData = [
                'section_id' => $section->id,
                'code' => 'SLF' . $i . '-' . now()->format('ymd') . rand(100, 999),
                'product_type' => $product_type,
                'shelf_width' => $request->input('largura_prateleira', 125),
                'shelf_height' => $request->input('altura_prateleira', 4),
                'shelf_depth' => $request->input('profundidade_prateleira', 40),
                'shelf_position' => round($position),
                'ordering' => $i,
                'settings' => [],
                'status' => $request->input('status', 'published'),
                'user_id' => auth()->id(),
                'tenant_id' => $section->tenant_id,
            ];

            $section->shelves()->create($shelfData);
        }
    }

    /**
     * Deleta gôndolas existentes com todas as relações
     * 
     * @param $gondolas
     * @return void
     */
    private function deleteExistingGondolas($gondolas): void
    {
        $gondolas->map(function ($gondola) {
            $this->deleteGondolaWithRelations($gondola);
        });
    }

    /**
     * Deleta uma gôndola com todas as suas relações
     * 
     * @param Gondola $gondola
     * @return void
     */
    private function deleteGondolaWithRelations(Gondola $gondola): void
    {
        $this->deleteSectionsAndShelves($gondola->sections);
        $gondola->forceDelete();
    }

    /**
     * Deleta seções e prateleiras
     * 
     * @param $sections
     * @return void
     */
    private function deleteSectionsAndShelves($sections): void
    {
        $sections->map(function ($section) {
            $section->shelves->map(function ($shelf) {
                // Delete segments and layers if they exist
                if (method_exists($shelf, 'segments') && $shelf->segments) {
                    $shelf->segments->map(function ($segment) {
                        if (method_exists($segment, 'layer') && $segment->layer) {
                            $segment->layer()->forceDelete();
                        }
                        $segment->forceDelete();
                    });
                }
                $shelf->forceDelete();
            });
            $section->forceDelete();
        });
    }

   
}
