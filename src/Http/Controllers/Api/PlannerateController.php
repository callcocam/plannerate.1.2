<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Http\Resources\PlannerateResource;
use Callcocam\Plannerate\Models\Gondola;
use Callcocam\Plannerate\Models\Layer;
use Callcocam\Plannerate\Models\Planogram;
use Callcocam\Plannerate\Models\Section;
use Callcocam\Plannerate\Models\Segment;
use Callcocam\Plannerate\Models\Shelf;
use Callcocam\Plannerate\Services\ShelfPositioningService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PlannerateController extends Controller
{

    /**
     * Exibe um planograma específico
     * 
     * @param Planogram $planogram
     * @return PlannerateResource|JsonResponse
     */
    public function show(string $id)
    {
        try { 
            $planogram = $this->getModel()::query()->with([
                'tenant',
                'store.store_map.gondolas',
                'cluster',
                'client', 
                'gondolas.sections.shelves.segments.layer.product', 
            ])->findOrFail($id); 

           
            return response()->json(new PlannerateResource($planogram));
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Planograma não encontrado',
                'status' => 'error'
            ], 404);
        } catch (Throwable $e) {
            Log::error('Erro ao exibir planograma', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }


    /**
     * Salva ou atualiza um planograma completo com toda a estrutura aninhada
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function save(Request $request, Planogram $planogram)
    {
        // Iniciar uma transação para garantir a consistência dos dados
        DB::beginTransaction();

        try {
            $data = $request->all();

            // Atualiza os atributos básicos do planograma
            $planogram->fill($this->filterPlanogramAttributes($data));
            $planogram->save();
            
            // Processa as gôndolas e sua estrutura aninhada de forma otimizada
            $this->processGondolasOptimized($planogram, data_get($data, 'gondolas', []));

            // Se chegou até aqui sem erros, confirma a transação
            DB::commit();

            // Buscar planograma atualizado com eager loading otimizado
            $planogram = $this->getModel()::query()->with([
                'tenant',
                'cluster',
                'client', 
                'gondolas.sections.shelves.segments.layer.product', 
            ])->findOrFail($planogram->id);
 
            return response()->json([
                'success' => true,
                'message' =>   'Planograma atualizado com sucesso',
                'data' => new PlannerateResource($planogram)
            ]);
        } catch (\Exception $e) {
            // Em caso de erro, reverte todas as alterações
            DB::rollBack();

            Log::error('Erro ao salvar planograma:', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar planograma: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'trace' => app()->environment('production') ? null : $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Filtra apenas os atributos pertinentes ao modelo Planogram
     * 
     * @param array $data
     * @return array
     */
    private function filterPlanogramAttributes(array $data): array
    {
        // Incluir apenas os campos que fazem parte da tabela planograms
        $fillable = [
            'name',
            'slug',
            'description',
            'store_id',
            'store',
            'cluster_id',
            'cluster',
            'start_date',
            'end_date',
            // 'status',
            // Adicione outros campos conforme necessário
        ];

        return array_intersect_key($data, array_flip($fillable));
    }

    /**
     * Processa as gôndolas e sua estrutura aninhada
     * 
     * @param Planogram $planogram
     * @param array $gondolas
     * @return void
     */
    private function processGondolas(Planogram $planogram, array $gondolas)
    {
        // Coletar IDs existentes para depois remover os que não estão mais presentes
        $existingGondolaIds = $planogram->gondolas()->pluck('id')->toArray();
        $processedGondolaIds = [];
        
        // Preparar dados para upsert em massa
        $gondolasToUpsert = [];
        $gondolasForSections = [];

        foreach ($gondolas as $gondolaData) {
            $gondolaId = data_get($gondolaData, 'id');
            $gondolaAttributes = $this->filterGondolaAttributes($gondolaData);
            
            // Se não tem ID, gerar um novo
            if (!$gondolaId) {
                $gondolaId = (string) Str::orderedUuid();
                $gondolaAttributes['id'] = $gondolaId;
                $gondolaAttributes['planogram_id'] = $planogram->id;
                $gondolaAttributes['tenant_id'] = $planogram->tenant_id;
                $gondolaAttributes['user_id'] = $planogram->user_id;
            }
            
            $gondolasToUpsert[] = $gondolaAttributes;
            $processedGondolaIds[] = $gondolaId;
            
            // Guardar dados para processar seções depois
            if (isset($gondolaData['sections'])) {
                $gondolasForSections[$gondolaId] = data_get($gondolaData, 'sections', []);
            }
        }

        // Upsert em massa das gôndolas
        if (!empty($gondolasToUpsert)) {
            Gondola::upsert(
                $gondolasToUpsert,
                ['id'], // Campos únicos para identificar registros existentes
                array_keys($this->filterGondolaAttributes([])) // Campos a serem atualizados
            );
        }

        // Processar seções de cada gôndola
        foreach ($gondolasForSections as $gondolaId => $sectionsData) {
            $gondola = Gondola::find($gondolaId);
            if ($gondola) {
                $this->processSections($gondola, $sectionsData);
            }
        }

        // Remover gôndolas que não estão mais presentes no planograma
        $gondolasToDelete = array_diff($existingGondolaIds, $processedGondolaIds);
        if (!empty($gondolasToDelete)) {
            Gondola::whereIn('id', $gondolasToDelete)->delete();
        }
    }

    /**
     * Filtra atributos da gôndola
     * 
     * @param array $data
     * @return array
     */
    private function filterGondolaAttributes(array $data = []): array
    {
        $fillable = [
            'id',
            'tenant_id',
            'user_id',
            'planogram_id',
            'linked_map_gondola_id',
            'linked_map_gondola_category',
            'name',
            'slug',
            'num_modulos',
            'location',
            'side',
            'flow',
            'alignment',
            'scale_factor',
            'status',
            'created_at',
            'updated_at'
        ];

        // Se não há dados, retorna apenas as chaves fillable para uso no upsert
        if (empty($data)) {
            return array_fill_keys($fillable, null);
        }

        $filtered = array_intersect_key($data, array_flip($fillable));
        
        // Extrair valor do status se vier como objeto
        if (isset($filtered['status']) && is_array($filtered['status'])) {
            $filtered['status'] = $filtered['status']['value'] ?? null;
        }
        
        // Adicionar timestamps se não existirem
        $now = now();
        if (!isset($filtered['created_at'])) {
            $filtered['created_at'] = $now;
        }
        $filtered['updated_at'] = $now;
        
        return $filtered;
    }

    /**
     * Processa as seções de uma gôndola
     * 
     * @param Gondola $gondola
     * @param array $sections
     * @return void
     */
    private function processSections(Gondola $gondola, array $sections): void
    {
        // Coletar IDs existentes para depois remover os que não estão mais presentes
        $existingSectionIds = $gondola->sections()->pluck('id')->toArray();
        $processedSectionIds = [];
        
        // Preparar dados para upsert em massa
        $sectionsToUpsert = [];
        $sectionsForShelves = [];
        $shelfService = new ShelfPositioningService();

        foreach ($sections as $i => $sectionData) {
            $sectionId = data_get($sectionData, 'id');
            
            // Se não tem ID, gerar um novo
            if (!$sectionId) {
                $sectionId = (string) Str::orderedUuid();
            }
            
            $sectionAttributes = $this->filterSectionAttributes($sectionData, $shelfService, $gondola);
            $sectionAttributes['id'] = $sectionId;
            $sectionAttributes['gondola_id'] = $gondola->id;
            $sectionAttributes['tenant_id'] = $gondola->tenant_id;
            $sectionAttributes['user_id'] = $gondola->user_id;
            $sectionAttributes['name'] = sprintf('%s# Sessão', $i);
            
            $sectionsToUpsert[] = $sectionAttributes;
            $processedSectionIds[] = $sectionId;
            
            // Guardar dados para processar prateleiras depois
            if (isset($sectionData['shelves'])) {
                $sectionsForShelves[$sectionId] = data_get($sectionData, 'shelves', []);
            }
        }

        // Upsert em massa das seções
        if (!empty($sectionsToUpsert)) {
            Section::upsert(
                $sectionsToUpsert,
                ['id'], // Campos únicos para identificar registros existentes
                array_keys($this->filterSectionAttributes([], $shelfService, $gondola)) // Campos a serem atualizados
            );
        }

        // Processar prateleiras de cada seção
        foreach ($sectionsForShelves as $sectionId => $shelvesData) {
            $section = Section::find($sectionId);
            if ($section) {
                $this->processShelves($section, $shelvesData, $shelfService);
            }
        }

        // Remover seções que não estão mais presentes na gôndola
        $sectionsToDelete = array_diff($existingSectionIds, $processedSectionIds);
        if (!empty($sectionsToDelete)) {
            Section::whereIn('id', $sectionsToDelete)->delete();
        }
    }

    /**
     * Filtra atributos da seção
     * 
     * @param array $data
     * @param ShelfPositioningService $shelfService
     * @return array
     */
    private function filterSectionAttributes(array $data = [], ShelfPositioningService $shelfService = null, Gondola $gondola = null): array
    {
        $fillable = [
            'id',
            'tenant_id',
            'user_id',
            'gondola_id',
            'name',
            'code',
            'slug',
            'width',
            'height',
            'num_shelves',
            'base_height',
            'base_depth',
            'base_width',
            'cremalheira_width',
            'hole_height',
            'hole_width',
            'hole_spacing',
            'ordering',
            'alignment',
            'settings',
            'status',
            'created_at',
            'updated_at'
        ];

        // Se não há dados, retorna apenas as chaves fillable para uso no upsert
        if (empty($data)) {
            return array_fill_keys($fillable, null);
        }

        $filtered = array_intersect_key($data, array_flip($fillable));
        
        // Extrair valor do status se vier como objeto
        if (isset($filtered['status']) && is_array($filtered['status'])) {
            $filtered['status'] = $filtered['status']['value'] ?? null;
        }
        
        // Processar settings se fornecido e se há dados
        if ($shelfService && !empty($data) && isset($data['settings'])) {
            $sectionSettings = $data['settings'] ?? [];
            $sectionSettings['holes'] = $shelfService->calculateHoles($data);
            $filtered['settings'] = json_encode($sectionSettings);
        } elseif (isset($filtered['settings']) && is_array($filtered['settings'])) {
            // Converter settings para JSON se for array
            $filtered['settings'] = json_encode($filtered['settings']);
        }
        
        // Adicionar timestamps se não existirem
        $now = now();
        if (!isset($filtered['created_at'])) {
            $filtered['created_at'] = $now;
        }
        $filtered['updated_at'] = $now;

        return $filtered;
    }

    /**
     * Processa as prateleiras de uma seção
     * 
     * @param Section $section
     * @param array $shelves
     * @return void
     */
    private function processShelves(Section $section, array $shelves, ShelfPositioningService $shelfService): void
    {
        // Coletar IDs existentes para depois remover os que não estão mais presentes
        $existingShelfIds = $section->shelves()->pluck('id')->toArray();
        $processedShelfIds = [];
        
        // Preparar dados para upsert em massa
        $shelvesToUpsert = [];
        $shelvesForSegments = [];

        foreach ($shelves as $i => $shelfData) {
            $shelfId = data_get($shelfData, 'id');
            
            // Se não tem ID, gerar um novo
            if (!$shelfId) {
                $shelfId = (string) Str::orderedUuid();
            }
            
            $shelfAttributes = $this->filterShelfAttributes($shelfData, $shelfService, $i, $section);
            $shelfAttributes['id'] = $shelfId;
            $shelfAttributes['section_id'] = $section->id;
            $shelfAttributes['tenant_id'] = $section->tenant_id;
            $shelfAttributes['user_id'] = $section->user_id;
            
            $shelvesToUpsert[] = $shelfAttributes;
            $processedShelfIds[] = $shelfId;
            
            // Guardar dados para processar segmentos depois
            if (isset($shelfData['segments'])) {
                $shelvesForSegments[$shelfId] = data_get($shelfData, 'segments', []);
            }
        }

        // Upsert em massa das prateleiras
        if (!empty($shelvesToUpsert)) {
            Shelf::upsert(
                $shelvesToUpsert,
                ['id'], // Campos únicos para identificar registros existentes
                array_keys($this->filterShelfAttributes([], $shelfService, 0, $section)) // Campos a serem atualizados
            );
        }

        // Processar segmentos de cada prateleira
        foreach ($shelvesForSegments as $shelfId => $segmentsData) {
            $shelf = Shelf::find($shelfId);
            if ($shelf) {
                $this->processSegments($shelf, $segmentsData);
            }
        }

        // Remover prateleiras que não estão mais presentes na seção
        $shelvesToDelete = array_diff($existingShelfIds, $processedShelfIds);
        if (!empty($shelvesToDelete)) {
            Shelf::whereIn('id', $shelvesToDelete)->delete();
        }
    }

    /**
     * Filtra atributos da prateleira
     * 
     * @param array $data
     * @param ShelfPositioningService $shelfService
     * @param int $i
     * @param Section $section
     * @return array
     */
    private function filterShelfAttributes(array $data = [], ShelfPositioningService $shelfService = null, int $i = 0, Section $section = null): array
    {
        $fillable = [
            'id',
            'tenant_id',
            'user_id',
            'section_id',
            'code',
            'product_type',
            'shelf_width',
            'shelf_height',
            'shelf_depth',
            'shelf_position',
            'ordering',
            'alignment',
            'spacing',
            'settings',
            'status',
            'created_at',
            'updated_at'
        ];
        
        // Se não há dados, retorna apenas as chaves fillable para uso no upsert
        if (empty($data)) {
            return array_fill_keys($fillable, null);
        }
        
        $filtered = array_intersect_key($data, array_flip($fillable));
        
        // Extrair valor do status se vier como objeto
        if (isset($filtered['status']) && is_array($filtered['status'])) {
            $filtered['status'] = $filtered['status']['value'] ?? null;
        }
        
        // Converter settings para JSON se for array
        if (isset($filtered['settings']) && is_array($filtered['settings'])) {
            $filtered['settings'] = json_encode($filtered['settings']);
        }
        
        // Adicionar timestamps se não existirem
        $now = now();
        if (!isset($filtered['created_at'])) {
            $filtered['created_at'] = $now;
        }
        $filtered['updated_at'] = $now;
        
        // $holes = data_get($section, 'settings.holes', []);
        // $position = $shelfService->calculateShelfPosition($section->num_shelves, data_get($data, 'shelf_height', 4), $holes, $i, $section->gondola->scale_factor);
        // $data['shelf_position'] = $position;

        return $filtered;
    }

    /**
     * Processa os segmentos de uma prateleira
     * 
     * @param Shelf $shelf
     * @param array $segments
     * @return void
     */
    private function processSegments(Shelf $shelf, array $segments): void
    {
        // Coletar IDs existentes para depois remover os que não estão mais presentes
        $existingSegmentIds = $shelf->segments()->pluck('id')->toArray();
        $processedSegmentIds = [];
        
        // Preparar dados para upsert em massa
        $segmentsToUpsert = [];
        $segmentsForLayers = [];

        foreach ($segments as $segmentData) {
            $segmentId = data_get($segmentData, 'id');
            
            // Se não tem ID ou é um ID temporário, gerar um novo
            if (!$segmentId || str_starts_with($segmentId, 'segment-')) {
                $segmentId = (string) Str::orderedUuid();
            }
            
            $segmentAttributes = $this->filterSegmentAttributes($segmentData);
            $segmentAttributes['id'] = $segmentId;
            $segmentAttributes['shelf_id'] = $shelf->id;
            $segmentAttributes['tenant_id'] = $shelf->tenant_id;
            $segmentAttributes['user_id'] = $shelf->user_id;
            
            $segmentsToUpsert[] = $segmentAttributes;
            $processedSegmentIds[] = $segmentId;
            
            // Guardar dados para processar camadas depois
            if (isset($segmentData['layer'])) {
                $segmentsForLayers[$segmentId] = data_get($segmentData, 'layer', []);
            }
        }

        // Upsert em massa dos segmentos
        if (!empty($segmentsToUpsert)) {
            Segment::upsert(
                $segmentsToUpsert,
                ['id'], // Campos únicos para identificar registros existentes
                array_keys($this->filterSegmentAttributes([])) // Campos a serem atualizados
            );
        }

        // Processar camadas de cada segmento
        foreach ($segmentsForLayers as $segmentId => $layerData) {
            $segment = Segment::find($segmentId);
            if ($segment) {
                $this->processLayer($segment, $layerData);
            }
        }

        // Remover segmentos que não estão mais presentes na prateleira
        $segmentsToDelete = array_diff($existingSegmentIds, $processedSegmentIds);
        if (!empty($segmentsToDelete)) {
            Segment::whereIn('id', $segmentsToDelete)->delete();
        }
    }

    /**
     * Filtra atributos do segmento
     * 
     * @param array $data
     * @return array
     */
    private function filterSegmentAttributes(array $data = []): array
    {
        $fillable = [
            'id',
            'tenant_id',
            'user_id',
            'shelf_id',
            'width',
            'distributed_width',
            'height',
            'ordering',
            'alignment',
            'position',
            'quantity',
            'spacing',
            'settings',
            'status',
            'created_at',
            'updated_at'
        ];

        // Se não há dados, retorna apenas as chaves fillable para uso no upsert
        if (empty($data)) {
            return array_fill_keys($fillable, null);
        }

        $filtered = array_intersect_key($data, array_flip($fillable));
        
        // Extrair valor do status se vier como objeto
        if (isset($filtered['status']) && is_array($filtered['status'])) {
            $filtered['status'] = $filtered['status']['value'] ?? null;
        }
        
        // Converter settings para JSON se for array
        if (isset($filtered['settings']) && is_array($filtered['settings'])) {
            $filtered['settings'] = json_encode($filtered['settings']);
        }
        
        // Adicionar timestamps se não existirem
        $now = now();
        if (!isset($filtered['created_at'])) {
            $filtered['created_at'] = $now;
        }
        $filtered['updated_at'] = $now;

        return $filtered;
    }

    /**
     * Processa a camada (layer) de um segmento
     * 
     * @param Segment $segment
     * @param array $layerData
     * @return void
     */
    private function processLayer(Segment $segment, array $layerData): void
    {
        if (empty($layerData)) {
            return;
        }
        
        $layerId = data_get($layerData, 'id');
        
        // Se não tem ID ou é um ID temporário, gerar um novo
        if (!$layerId || str_starts_with($layerId, 'layer-')) {
            $layerId = (string) Str::orderedUuid();
        }
        
        $layerAttributes = $this->filterLayerAttributes($layerData);
        $layerAttributes['id'] = $layerId;
        $layerAttributes['segment_id'] = $segment->id;
        $layerAttributes['tenant_id'] = $segment->tenant_id;
        $layerAttributes['user_id'] = $segment->user_id;
        
        // Usar upsert para uma única camada também
        Layer::upsert(
            [$layerAttributes],
            ['id'], // Campos únicos
            array_keys($this->filterLayerAttributes([])) // Campos a serem atualizados
        );
    }

    /**
     * Filtra atributos da camada (layer)
     * 
     * @param array $data
     * @return array
     */
    private function filterLayerAttributes(array $data = []): array
    {
        $fillable = [
            'id',
            'tenant_id',
            'user_id',
            'segment_id',
            'product_id',
            'height',
            'distributed_width',
            'quantity',
            'alignment',
            'spacing',
            'settings',
            'status',
            'created_at',
            'updated_at'
        ];

        // Se não há dados, retorna apenas as chaves fillable para uso no upsert
        if (empty($data)) {
            return array_fill_keys($fillable, null);
        }

        $filtered = array_intersect_key($data, array_flip($fillable));
        
        // Extrair valor do status se vier como objeto
        if (isset($filtered['status']) && is_array($filtered['status'])) {
            $filtered['status'] = $filtered['status']['value'] ?? null;
        }
        
        // Extrair o product_id de objetos aninhados, se necessário
        // if (isset($data['product']) && isset($data['product']['id']) && !isset($data['product_id'])) {
        //     $data['product_id'] = $data['product']['id'];
        // }

        // Converter settings para JSON se for array
        if (isset($filtered['settings']) && is_array($filtered['settings'])) {
            $filtered['settings'] = json_encode($filtered['settings']);
        }
        
        // Adicionar timestamps se não existirem
        $now = now();
        if (!isset($filtered['created_at'])) {
            $filtered['created_at'] = $now;
        }
        $filtered['updated_at'] = $now;

        return $filtered;
    }

    protected function getModel()
    {
        if (class_exists('App\Models\Planogram')) {
            return 'App\Models\Planogram';
        }
        return Planogram::class;
    }

    /**
     * Método otimizado para processar gôndolas com performance melhorada
     * Utiliza batch processing e reduz consultas ao banco
     * 
     * @param Planogram $planogram
     * @param array $gondolas
     * @return void
     */
    private function processGondolasOptimized(Planogram $planogram, array $gondolas): void
    {
        if (empty($gondolas)) {
            return;
        }

        // 1. Preparar todos os dados de uma vez
        $allGondolasData = [];
        $allSectionsData = [];
        $allShelvesData = [];
        $allSegmentsData = [];
        $allLayersData = [];
        
        $shelfService = new ShelfPositioningService();
        $now = now();

        foreach ($gondolas as $gondolaIndex => $gondolaData) {
            $gondolaId = data_get($gondolaData, 'id') ?: (string) Str::orderedUuid();
            
            // Preparar dados da gôndola
            $gondolaAttributes = $this->filterGondolaAttributes($gondolaData);
            $gondolaAttributes['id'] = $gondolaId;
            $gondolaAttributes['planogram_id'] = $planogram->id;
            $gondolaAttributes['tenant_id'] = $planogram->tenant_id;
            $gondolaAttributes['user_id'] = $planogram->user_id;
            $allGondolasData[] = $gondolaAttributes;

            // Processar seções
            foreach (data_get($gondolaData, 'sections', []) as $sectionIndex => $sectionData) {
                $sectionId = data_get($sectionData, 'id') ?: (string) Str::orderedUuid();
                
                $sectionAttributes = $this->filterSectionAttributes($sectionData, $shelfService, null);
                $sectionAttributes['id'] = $sectionId;
                $sectionAttributes['gondola_id'] = $gondolaId;
                $sectionAttributes['tenant_id'] = $planogram->tenant_id;
                $sectionAttributes['user_id'] = $planogram->user_id;
                $sectionAttributes['name'] = sprintf('%s# Sessão', $sectionIndex);
                $allSectionsData[] = $sectionAttributes;

                // Processar prateleiras
                foreach (data_get($sectionData, 'shelves', []) as $shelfIndex => $shelfData) {
                    $shelfId = data_get($shelfData, 'id') ?: (string) Str::orderedUuid();
                    
                    $shelfAttributes = $this->filterShelfAttributes($shelfData, $shelfService, $shelfIndex, null);
                    $shelfAttributes['id'] = $shelfId;
                    $shelfAttributes['section_id'] = $sectionId;
                    $shelfAttributes['tenant_id'] = $planogram->tenant_id;
                    $shelfAttributes['user_id'] = $planogram->user_id;
                    $allShelvesData[] = $shelfAttributes;

                    // Processar segmentos
                    foreach (data_get($shelfData, 'segments', []) as $segmentData) {
                        $segmentId = data_get($segmentData, 'id');
                        if (!$segmentId || str_starts_with($segmentId, 'segment-')) {
                            $segmentId = (string) Str::orderedUuid();
                        }
                        
                        $segmentAttributes = $this->filterSegmentAttributes($segmentData);
                        $segmentAttributes['id'] = $segmentId;
                        $segmentAttributes['shelf_id'] = $shelfId;
                        $segmentAttributes['tenant_id'] = $planogram->tenant_id;
                        $segmentAttributes['user_id'] = $planogram->user_id;
                        $allSegmentsData[] = $segmentAttributes;

                        // Processar camada
                        $layerData = data_get($segmentData, 'layer', []);
                        if (!empty($layerData)) {
                            $layerId = data_get($layerData, 'id');
                            if (!$layerId || str_starts_with($layerId, 'layer-')) {
                                $layerId = (string) Str::orderedUuid();
                            }
                            
                            $layerAttributes = $this->filterLayerAttributes($layerData);
                            $layerAttributes['id'] = $layerId;
                            $layerAttributes['segment_id'] = $segmentId;
                            $layerAttributes['tenant_id'] = $planogram->tenant_id;
                            $layerAttributes['user_id'] = $planogram->user_id;
                            $allLayersData[] = $layerAttributes;
                        }
                    }
                }
            }
        }

        // 2. Executar todas as operações de upsert em massa
        $this->executeBatchUpserts($planogram, [
            'gondolas' => $allGondolasData,
            'sections' => $allSectionsData,
            'shelves' => $allShelvesData,
            'segments' => $allSegmentsData,
            'layers' => $allLayersData
        ]);
    }

    /**
     * Executa upserts em massa para melhor performance
     * 
     * @param Planogram $planogram
     * @param array $batchData
     * @return void
     */
    private function executeBatchUpserts(Planogram $planogram, array $batchData): void
    {
        // Coletar IDs existentes para limpeza
        $existingGondolaIds = $planogram->gondolas()->pluck('id')->toArray();
        $processedGondolaIds = array_column($batchData['gondolas'], 'id');

        // Executar upserts em ordem hierárquica
        if (!empty($batchData['gondolas'])) {
            // Dividir em chunks para evitar problemas de memória
            foreach (array_chunk($batchData['gondolas'], 100) as $chunk) {
                Gondola::upsert($chunk, ['id'], array_keys($this->filterGondolaAttributes([])));
            }
        }

        if (!empty($batchData['sections'])) {
            foreach (array_chunk($batchData['sections'], 100) as $chunk) {
                Section::upsert($chunk, ['id'], array_keys($this->filterSectionAttributes([])));
            }
        }

        if (!empty($batchData['shelves'])) {
            foreach (array_chunk($batchData['shelves'], 100) as $chunk) {
                Shelf::upsert($chunk, ['id'], array_keys($this->filterShelfAttributes([])));
            }
        }

        if (!empty($batchData['segments'])) {
            foreach (array_chunk($batchData['segments'], 100) as $chunk) {
                Segment::upsert($chunk, ['id'], array_keys($this->filterSegmentAttributes([])));
            }
        }

        if (!empty($batchData['layers'])) {
            foreach (array_chunk($batchData['layers'], 100) as $chunk) {
                Layer::upsert($chunk, ['id'], array_keys($this->filterLayerAttributes([])));
            }
        }

        // Limpeza de registros órfãos
        $this->cleanupOrphanedRecords($existingGondolaIds, $processedGondolaIds);
    }

    /**
     * Remove registros órfãos que não fazem mais parte da estrutura
     * 
     * @param array $existingGondolaIds
     * @param array $processedGondolaIds
     * @return void
     */
    private function cleanupOrphanedRecords(array $existingGondolaIds, array $processedGondolaIds): void
    {
        $gondolasToDelete = array_diff($existingGondolaIds, $processedGondolaIds);
        
        if (!empty($gondolasToDelete)) {
            // O delete em cascata cuidará dos relacionamentos
            Gondola::whereIn('id', $gondolasToDelete)->delete();
        }
    }
}
