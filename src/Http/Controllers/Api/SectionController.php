<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use Callcocam\Plannerate\Enums\SectionStatus;
use Callcocam\Plannerate\Http\Requests\Section\StoreSectionRequest;
use Callcocam\Plannerate\Http\Requests\Section\UpdateSectionRequest;
use Callcocam\Plannerate\Http\Resources\SectionResource;
use Callcocam\Plannerate\Models\Gondola;
use Callcocam\Plannerate\Models\Section;
use Callcocam\Plannerate\Services\ShelfPositioningService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SectionController extends Controller
{
    /**
     * Exibe a listagem das seções de uma gôndola
     *
     * @param string $gondolaId
     * @return AnonymousResourceCollection|JsonResponse
     */
    public function index(string $gondolaId)
    {
        try {
            // Verificar se a gôndola existe
            $gondola = Gondola::findOrFail($gondolaId);

            $query = Section::query()
                ->where('gondola_id', $gondolaId)
                ->orderBy('ordering', 'asc');

            // Aplicar filtros
            if (request()->has('search')) {
                $search = request()->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            }

            if (request()->has('status')) {
                $query->where('status', request()->input('status'));
            }

            $perPage = request()->input('per_page', 15);
            $data = $query->paginate($perPage);

            return $this->handleSuccess('Seções carregadas com sucesso', [
                'data' => SectionResource::collection($data)
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->handleNotFoundException('Gôndola não encontrada');
        } catch (Throwable $e) {
            return $this->handleInternalServerError('Ocorreu um erro ao carregar as seções');
        }
    }

    /**
     * Exibe uma seção específica
     *
     * @param string $gondolaId
     * @param string $id
     * @return SectionResource|JsonResponse
     */
    public function show(string $gondolaId, string $id)
    {
        try {
            // Verificar se a gôndola existe
            Gondola::findOrFail($gondolaId);

            $section = Section::with(['shelves'])
                ->where('gondola_id', $gondolaId)
                ->findOrFail($id);

            return $this->handleSuccess('Seção carregada com sucesso', [
                'data' => new SectionResource($section)
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->handleNotFoundException('Seção ou gôndola não encontrada');
        } catch (Throwable $e) {
            return $this->handleInternalServerError('Ocorreu um erro ao carregar a seção');
        }
    }

    /**
     * Armazena uma nova seção
     *
     * @param StoreSectionRequest $request
     * @param string $gondolaId
     * @return SectionResource|JsonResponse
     */
    public function store(StoreSectionRequest $request)
    {
        try {
            DB::beginTransaction();

            // Verificar se a gôndola existe
            $gondola = Gondola::findOrFail($request->input('gondola_id'));

            // Validar dados
            $validatedData = $request->validated();

            // Obter a última ordem se não fornecida 
            $lastOrdering = $this->getLastOrdering($gondola->id);

            // Criar a seção com configurações adequadas
            $section = $this->createSection($gondola, $validatedData, $lastOrdering);

            // Criar prateleiras para a seção
            $this->createShelvesForSection($section, $request);

            DB::commit();

            // Carregar relacionamentos para o retorno
            $section = $section->fresh(['gondola', 'shelves']);

            return $this->handleSuccess('Seção criada com sucesso', [
                'data' => new SectionResource($section)
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->handleNotFoundException('Gôndola não encontrada');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Erro ao criar seção', $request->all());
        }
    }


    /**
     * Atualiza uma seção existente
     *
     * @param UpdateSectionRequest $request
     * @param string $gondolaId
     * @param string $id
     * @return SectionResource|JsonResponse
     */
    public function update(UpdateSectionRequest $request, string $gondolaId, string $id)
    {
        try {
            DB::beginTransaction();

            // Verificar se a gôndola existe
            Gondola::findOrFail($gondolaId);

            // Buscar a seção
            $section = Section::where('gondola_id', $gondolaId)->findOrFail($id);

            // Validar dados
            $validatedData = $request->validated();

            // Atualizar slug se o nome foi alterado
            if (isset($validatedData['name']) && $section->name !== $validatedData['name']) {
                $validatedData['slug'] = Str::slug($validatedData['name']);
            }

            // Verificar se algum campo relacionado aos furos da cremalheira foi alterado
            $holesRelatedFields = ['hole_height', 'hole_width', 'hole_spacing', 'height', 'base_height'];
            $shouldRecalculateHoles = false;
            
            foreach ($holesRelatedFields as $field) {
                if (isset($validatedData[$field]) && $validatedData[$field] != $section->$field) {
                    $shouldRecalculateHoles = true;
                    break;
                }
            }

            // Se campos relacionados aos furos foram alterados, recalcular os furos
            if ($shouldRecalculateHoles) {
                $shelfService = new ShelfPositioningService();
                
                // Preparar dados para o cálculo dos furos (mesclar dados existentes com novos)
                $holeCalculationData = [
                    'height' => $validatedData['height'] ?? $section->height,
                    'hole_height' => $validatedData['hole_height'] ?? $section->hole_height,
                    'hole_width' => $validatedData['hole_width'] ?? $section->hole_width,
                    'hole_spacing' => $validatedData['hole_spacing'] ?? $section->hole_spacing,
                    'base_height' => $validatedData['base_height'] ?? $section->base_height,
                ];

                // Recalcular os furos
                $newHoles = $shelfService->calculateHoles($holeCalculationData);
                
                // Atualizar ou criar settings com os novos furos
                $currentSettings = $section->settings ?? [];
                $currentSettings['holes'] = $newHoles;
                $validatedData['settings'] = $currentSettings;
            }

            // Atualizar a seção
            $section->update($validatedData);

            // Atualizar prateleiras se necessário
            if (isset($validatedData['num_shelves'])) {
                // Obter o número atual de prateleiras
                $currentShelves = $section->shelves()->count();
                $numShelves = $validatedData['num_shelves'];

                if ($numShelves > $currentShelves) {
                    // Adicionar novas prateleiras
                    $shelfHeight = $validatedData['shelf_height'] ?? $section->shelf_height ?? 4;
                    $shelves = [];
                    for ($i = $currentShelves; $i < $numShelves; $i++) {
                        $shelves[] = [
                            'id' => (string) Str::ulid(),
                            'section_id' => $section->id,
                            'name' => "Prateleira " . ($i + 1),
                            'position' => $i,
                            'height' => $shelfHeight,
                            'width' => $validatedData['width'] ?? $section->width ?? 130,
                            'depth' => $validatedData['shelf_depth'] ?? 40,
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
                if (isset($validatedData['width']) || isset($validatedData['shelf_height'])) {
                    $section->shelves()->update([
                        'height' => $validatedData['shelf_height'] ?? $section->shelf_height ?? 4,
                        'width' => $validatedData['width'] ?? $section->width ?? 130,
                    ]);
                }
            }

            DB::commit();

            // Carregar relacionamentos para o retorno
            $section = $section->fresh(['gondola', 'shelves']);

            return $this->handleSuccess('Seção atualizada com sucesso', [
                'data' => new SectionResource($section)
            ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Ocorreu um erro ao atualizar a seção');
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Ocorreu um erro ao atualizar a seção');
        }
    }

    /**
     * Remove uma seção
     *
     * @param string $gondolaId
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(Section $section)
    {
        try {
            DB::beginTransaction();

            // Buscar a seção
            $section->shelves->map(function ($shelf) {
                $shelf->segments->map(function ($segment) {
                    $segment->layer()->delete();
                    $segment->delete();
                });
                $shelf->delete();
            });

            // Excluir seção (soft delete)
            $section->delete();

            DB::commit();

            return $this->handleSuccess('Seção excluída com sucesso');
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->handleNotFoundException('Seção ou gôndola não encontrada');
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Ocorreu um erro ao excluir a seção');
        }
    }


    /**
     * Obtém o último número de ordenação para seções de uma gôndola
     * 
     * @param string $gondolaId
     * @return int
     */
    private function getLastOrdering(string $gondolaId): int
    {
        return Section::where('gondola_id', $gondolaId)->max('ordering') ?? 0;
    }

    /**
     * Cria uma nova seção
     * 
     * @param Gondola $gondola
     * @param array $validatedData
     * @param int $lastOrdering
     * @return Section
     */
    private function createSection(Gondola $gondola, array $validatedData, int $lastOrdering): Section
    {
        // Configurar serviço de posicionamento para cálculo de furos
        $shelfService = new ShelfPositioningService();

        // Preparar configurações da seção
        $sectionSettings = [
            'holes' => $shelfService->calculateHoles([
                'height' => $validatedData['height'] ?? 180,
                'hole_height' => $validatedData['hole_height'] ?? 3,
                'hole_spacing' => $validatedData['hole_spacing'] ?? 2,
                'num_shelves' => $validatedData['num_shelves'] ?? 4,
                'hole_width' => $validatedData['hole_width'] ?? 2,
                'base_height' => $validatedData['base_height'] ?? 17,
            ], $gondola->scale_factor)
        ];

        // Preparar dados da seção
        $sectionData = [
            'gondola_id' => $gondola->id,
            'name' => $validatedData['name'] ?? 'SEC-' . now()->format('ymd') . rand(1000, 9999) . ' - Section',
            'code' => $validatedData['code'] ?? 'SEC-' . rand(100000, 999999),
            'width' => $validatedData['width'] ?? 130,
            'height' => $validatedData['height'] ?? 180,
            'num_shelves' => $validatedData['num_shelves'] ?? 4,
            'base_height' => $validatedData['base_height'] ?? 17,
            'base_depth' => $validatedData['base_depth'] ?? 40,
            'base_width' => $validatedData['base_width'] ?? 130,
            'cremalheira_width' => $validatedData['cremalheira_width'] ?? 4,
            'hole_height' => $validatedData['hole_height'] ?? 3,
            'hole_width' => $validatedData['hole_width'] ?? 2,
            'hole_spacing' => $validatedData['hole_spacing'] ?? 2,
            'ordering' => $lastOrdering + 1,
            'settings' => $sectionSettings,
            'status' => $validatedData['status'] ?? 'published',
            'user_id' => auth()->id(),
            'tenant_id' => $gondola->tenant_id,
        ];

        return Section::create($sectionData);
    }

    /**
     * Cria prateleiras para uma seção
     * 
     * @param Section $section
     * @param Request $request
     * @return void
     */
    private function createShelvesForSection(Section $section, Request $request): void
    {
        $shelfQty = $request->input('num_shelves', 4);
        $productType = $request->input('product_type', 'normal');

        if ($shelfQty > 0) {
            // Configurar serviço de posicionamento
            $shelfService = new ShelfPositioningService();

            // Criar prateleiras
            for ($i = 0; $i < $shelfQty; $i++) {
                // Calcular posição vertical da prateleira
                $position = $shelfService->calculateShelfPosition(
                    $shelfQty,
                    $request->input('shelf_height', 4),
                    $section->settings['holes'] ?? [],
                    $i,
                    $section->gondola->scale_factor
                );

                $shelfData = [
                    'section_id' => $section->id,
                    'code' => 'SLF' . $i . '-' . now()->format('ymd') . rand(100, 999),
                    'product_type' => $productType,
                    'shelf_width' => $request->input('shelf_width', 125),
                    'shelf_height' => $request->input('shelf_height', 4),
                    'shelf_depth' => $request->input('shelf_depth', 40),
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
    }
}
