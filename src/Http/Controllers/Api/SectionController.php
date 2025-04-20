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
            $gondola = Gondola::findOrFail($request->input('gondola_id'));;

            // Validar dados
            $validatedData = $request->validated();

            // Obter a última ordem se não fornecida 
            $lastOrdering = Section::where('gondola_id', $gondola->id)
                ->max('ordering') ?? 0;
            $validatedData['ordering'] = $lastOrdering + 1;

            // Criar seções se fornecidas
            $shelfService =  new ShelfPositioningService();
            if (!isset($validatedData['settings'])) $validatedData['settings'] = [];
            $sectionSettings =   [];
            $sectionSettings['holes'] = $shelfService->calculateHoles($validatedData, $gondola->scale_factor);
            $validatedData['settings'] = $sectionSettings;
            // Criar a seção
            $section = Section::create($validatedData);

            // Criar prateleiras se necessário
            if (isset($validatedData['num_shelves']) && $validatedData['num_shelves'] > 0) {
                $shelfHeight = $validatedData['shelf_height'] ?? 4;
                $shelves = [];
                for ($i = 0; $i < $validatedData['num_shelves']; $i++) {
                    $shelves[] = [
                        'id' => (string) Str::ulid(),
                        'section_id' => $section->id,
                        'code' => uniqid($i),
                        'shelf_position' => $i,
                        'shelf_height' => $shelfHeight,
                        'shelf_width' => $validatedData['width'] ?? 130,
                        'shelf_depth' => $validatedData['shelf_depth'] ?? 40,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (!empty($shelves)) {
                    $section->shelves()->insert($shelves);
                }
            }

            DB::commit();

            // Carregar relacionamentos para o retorno
            $section = $section->fresh(['gondola', 'shelves']);

            return $this->handleSuccess('Seção criada com sucesso', [
                'data' => new SectionResource($section)
            ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Ocorreu um erro ao criar a seção');
        } catch (Throwable $e) {
            DB::rollBack();
            return $this->handleInternalServerError('Ocorreu um erro ao criar a seção');
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
}
