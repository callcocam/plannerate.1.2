<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services\Plannerate;

use Callcocam\Plannerate\Models\Gondola;
use Callcocam\Plannerate\Models\Layer;
use Callcocam\Plannerate\Models\Section;
use Callcocam\Plannerate\Models\Segment;
use Callcocam\Plannerate\Models\Shelf;
use Callcocam\Plannerate\Services\ShelfPositioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlannerateUpdateSevice
{

    protected $user = null;

    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Factory method para criar uma instância do serviço
     */
    public static function make($user): PlannerateUpdateSevice
    {
        return new static($user);
    }

    /**
     * Atualiza o planograma completo com comparação e limpeza de órfãos
     *
     * @param Request $request
     * @param mixed $planogram (App\Models\Planogram ou Callcocam\Plannerate\Models\Planogram)
     * @return void
     * @throws \Exception
     */
    public function update($data, $planogram): void
    {
        DB::beginTransaction();

        try {

            // Atualiza os atributos básicos do planograma
            $planogram->fill($this->filterPlanogramAttributes($data));
            $planogram->save();

            Log::info('✅ [UPDATE SERVICE] Planograma atualizado', [
                'planogram_id' => $planogram->id,
            ]);

            // Processa as gôndolas e sua estrutura aninhada
            $this->processGondolas($planogram, data_get($data, 'gondolas', []));

            DB::commit();

            Log::info('🎉 [UPDATE SERVICE] Atualização do planograma concluída com sucesso', [
                'planogram_id' => $planogram->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ [UPDATE SERVICE] Erro ao atualizar planograma', [
                'planogram_id' => $planogram->id ?? null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
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
        $fillable = [
            'name',
            'slug',
            'description',
            'store_id',
            'cluster_id',
            'start_date',
            'end_date',
            'status',
        ];

        $filtered = array_intersect_key($data, array_flip($fillable));

        // Fix: Se status vier como array {value, label, color}, extrair apenas o value
        if (isset($filtered['status']) && is_array($filtered['status'])) {
            $filtered['status'] = $filtered['status']['value'] ?? null;
        }

        return $filtered;
    }

    /**
     * Processa as gôndolas e sua estrutura aninhada
     * Remove gôndolas órfãs que não estão mais presentes no frontend
     *
     * @param mixed $planogram
     * @param array $gondolas
     * @return void
     */
    private function processGondolas($planogram, array $gondolas): void
    {
        // Buscar IDs das gôndolas existentes no banco
        $existingGondolaIds = Gondola::query()
            ->where('planogram_id', $planogram->id)
            ->pluck('id')
            ->toArray();

        $processedGondolaIds = [];
        $createdCount = 0;
        $updatedCount = 0;

        // Bulk loading - carregar todas as gondolas existentes de uma vez
        $gondolaIds = array_filter(array_column($gondolas, 'id'));
        $existingGondolas = Gondola::query()
            ->whereIn('id', $gondolaIds)
            ->get()
            ->keyBy('id');

        foreach ($gondolas as $index => $gondolaData) {
            $gondolaId = data_get($gondolaData, 'id');
            $gondola = $existingGondolas->get($gondolaId);

            if (!$gondola) {
                // Criar nova gôndola
                $gondola = new Gondola();
                $gondola->id = (string) Str::ulid();
                $gondola->tenant_id = $planogram->tenant_id;
                $gondola->user_id = $planogram->user_id;
                $gondola->planogram_id = $planogram->id;
                $createdCount++;
            } else {
                $updatedCount++;
            }

            // Atualizar atributos da gôndola
            $gondola->timestamps = false; // Desabilitar timestamps para performance
            $gondola->fill($this->filterGondolaAttributes($gondolaData));
            $gondola->save();

            // Registrar ID processado
            $processedGondolaIds[] = $gondola->id;

            // Processar seções desta gôndola
            if (isset($gondolaData['sections'])) {
                $this->processSections($gondola, data_get($gondolaData, 'sections', []));
            }
        }

        // Identificar e remover gôndolas órfãs
        $gondolasToDelete = array_diff($existingGondolaIds, $processedGondolaIds);

        if (!empty($gondolasToDelete)) {
            Log::warning('🗑️ [GONDOLAS] Removendo gôndolas órfãs', [
                'orphan_count' => count($gondolasToDelete),
            ]);
            Gondola::whereIn('id', $gondolasToDelete)->delete();
        }

        // Log de resumo
        Log::info('✅ [GONDOLAS] Processamento concluído', [
            'created' => $createdCount,
            'updated' => $updatedCount,
            'deleted' => count($gondolasToDelete),
        ]);
    }

    /**
     * Filtra atributos da gôndola
     *
     * @param array $data
     * @return array
     */
    private function filterGondolaAttributes(array $data): array
    {
        $fillable = [
            'name',
            'slug',
            'location',
            'side',
            'flow',
            'num_modulos',
            'scale_factor',
            'alignment',
            'status',
            'linked_map_gondola_id',
            'linked_map_gondola_category',
        ];

        $filtered = array_intersect_key($data, array_flip($fillable));

        // Fix: Se status vier como array {value, label, color}, extrair apenas o value
        if (isset($filtered['status']) && is_array($filtered['status'])) {
            $filtered['status'] = $filtered['status']['value'] ?? null;
        }

        return $filtered;
    }

    /**
     * Processa as seções de uma gôndola
     * Remove seções órfãs que não estão mais presentes no frontend
     *
     * @param Gondola $gondola
     * @param array $sections
     * @return void
     */
    private function processSections(Gondola $gondola, array $sections): void
    {
        // Buscar IDs das seções existentes no banco
        $existingSectionIds = Section::query()
            ->where('gondola_id', $gondola->id)
            ->pluck('id')
            ->toArray();

        $processedSectionIds = [];
        $shelfService = new ShelfPositioningService();
        $createdCount = 0;
        $updatedCount = 0;

        // Bulk loading - carregar todas as sections de uma vez
        $sectionIds = array_filter(array_column($sections, 'id'));
        $existingSections = Section::query()
            ->whereIn('id', $sectionIds)
            ->get()
            ->keyBy('id');

        foreach ($sections as $i => $sectionData) {
            $sectionId = data_get($sectionData, 'id');
            $section = $existingSections->get($sectionId);

            if (!$section) {
                // Criar nova seção
                $section = Section::query()->create([
                    'id' => (string) Str::ulid(),
                    'tenant_id' => $gondola->tenant_id,
                    'user_id' => $gondola->user_id,
                    'gondola_id' => $gondola->id,
                    'name' => data_get($sectionData, 'name', "Seção #{$i}"),
                ]);
                $createdCount++;
            } else {
                $updatedCount++;
            }

            // Atualizar atributos da seção
            $data = $this->filterSectionAttributes($sectionData, $shelfService, $gondola);
            $data['gondola_id'] = $gondola->id;
            $data['name'] = sprintf('%d# Sessão', $i);
            $section->timestamps = false; // Desabilitar timestamps para performance
            if ($section->update($data)) {
                activity('plannerate')
                    ->causedBy($this->user)
                    ->performedOn($section) 
                    ->log('Seção atualizada via PlannerateUpdateService');
            }


            // Registrar ID processado
            $processedSectionIds[] = $section->id;

            // Processar prateleiras desta seção
            if (isset($sectionData['shelves'])) {
                $this->processShelves($section, data_get($sectionData, 'shelves', []), $shelfService);
            }
        }

        // Identificar e remover seções órfãs
        $sectionsToDelete = array_diff($existingSectionIds, $processedSectionIds);

        if (!empty($sectionsToDelete)) {
            Section::whereIn('id', $sectionsToDelete)->delete();
        }

        // Log de resumo apenas se houver operações relevantes
        if ($createdCount > 0 || count($sectionsToDelete) > 0) {
            Log::info('✅ [SECTIONS] Processamento concluído', [
                'gondola_id' => $gondola->id,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'deleted' => count($sectionsToDelete),
            ]);
        }
    }

    /**
     * Filtra atributos da seção
     *
     * @param array $data
     * @param ShelfPositioningService $shelfService
     * @param Gondola $gondola
     * @return array
     */
    private function filterSectionAttributes(array $data, ShelfPositioningService $shelfService, Gondola $gondola): array
    {
        $fillable = [
            'name' => data_get($data, 'name', 'Seção'),
            'slug' => data_get($data, 'slug', Str::slug(data_get($data, 'name', 'seccao'))),
            'width' => data_get($data, 'width', 130),
            'height' => data_get($data, 'height', 180),
            'num_shelves' => data_get($data, 'num_shelves', 4),
            'base_height' => data_get($data, 'base_height', 10),
            'base_depth' => data_get($data, 'base_depth', 20),
            'base_width' => data_get($data, 'base_width', 130),
            'hole_height' => data_get($data, 'hole_height', 4),
            'hole_width' => data_get($data, 'hole_width', 2),
            'hole_spacing' => data_get($data, 'hole_spacing', 2),
            'shelf_height' => data_get($data, 'shelf_height', 4),
            'cremalheira_width' => data_get($data, 'cremalheira_width', 2),
            'ordering' => data_get($data, 'ordering', 0),
        ];

        // Calcular furos e adicionar às configurações
        $sectionSettings = data_get($data, 'settings', []);
        $sectionSettings['holes'] = $shelfService->calculateHoles($fillable);
        $fillable['settings'] = $sectionSettings;

        return $fillable;
    }

    /**
     * Processa as prateleiras de uma seção
     * Remove prateleiras órfãs que não estão mais presentes no frontend
     *
     * @param Section $section
     * @param array $shelves
     * @param ShelfPositioningService $shelfService
     * @return void
     */
    private function processShelves(Section $section, array $shelves, ShelfPositioningService $shelfService): void
    {
        // Buscar IDs das prateleiras existentes no banco
        $existingShelfIds = Shelf::query()
            ->where('section_id', $section->id)
            ->pluck('id')
            ->toArray();

        $processedShelfIds = [];
        $createdCount = 0;
        $updatedCount = 0;

        // Bulk loading - carregar todas as shelves de uma vez
        $shelfIds = array_filter(array_column($shelves, 'id'));
        $existingShelves = Shelf::query()
            ->whereIn('id', $shelfIds)
            ->get()
            ->keyBy('id');

        foreach ($shelves as $i => $shelfData) {
            $shelfId = data_get($shelfData, 'id');
            $shelf = $existingShelves->get($shelfId);

            if (!$shelf) {
                // Criar nova prateleira
                $shelf = Shelf::query()->create([
                    'id' => (string) Str::ulid(),
                    'tenant_id' => $section->tenant_id,
                    'user_id' => $section->user_id,
                    'section_id' => $section->id,
                ]);
                $createdCount++;
            } else {
                $updatedCount++;
            }

            // Atualizar atributos da prateleira
            $data = $this->filterShelfAttributes($shelfData, $shelfService, $i, $section);
            $data['section_id'] = $section->id;
            $shelf->timestamps = false; // Desabilitar timestamps para performance
           if ($shelf->update($data)) {
                activity('plannerate')
                    ->causedBy($this->user)
                    ->performedOn($shelf) 
                    ->log('Prateleira atualizada via PlannerateUpdateService');
            }

            // Registrar ID processado
            $processedShelfIds[] = $shelf->id;

            // Processar segmentos desta prateleira (mantém batch nos segments)
            if (isset($shelfData['segments'])) {
                $this->processSegments($shelf, data_get($shelfData, 'segments', []));
            }
        }

        // Identificar e remover prateleiras órfãs
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
    private function filterShelfAttributes(array $data, ShelfPositioningService $shelfService, int $i, Section $section): array
    {
        // Fix: Extrair status.value se vier como array
        $status = data_get($data, 'status', 'published');
        if (is_array($status)) {
            $status = $status['value'] ?? 'published';
        }

        $fillable = [
            'product_type' => data_get($data, 'product_type', 'generic'),
            'shelf_width' => data_get($data, 'shelf_width', 130),
            'shelf_height' => data_get($data, 'shelf_height', 4),
            'shelf_depth' => data_get($data, 'shelf_depth', 20),
            'shelf_position' => data_get($data, 'shelf_position', 0),
            'ordering' => data_get($data, 'ordering', 0),
            'spacing' => data_get($data, 'spacing', 2),
            'settings' => data_get($data, 'settings', []),
            'status' => $status,
            'alignment' => data_get($data, 'alignment', 'left'),
        ];

        return $fillable;
    }

    /**
     * Processa os segmentos de uma prateleira
     * Remove segmentos órfãos que não estão mais presentes no frontend
     *
     * @param Shelf $shelf
     * @param array $segments
     * @return void
     */
    private function processSegments(Shelf $shelf, array $segments): void
    {
        // Buscar IDs dos segmentos existentes no banco
        $existingSegmentIds = Segment::query()
            ->where('shelf_id', $shelf->id)
            ->pluck('id')
            ->toArray();

        $processedSegmentIds = [];
        $createdCount = 0;
        $updatedCount = 0;
        $segmentsToUpsert = [];
        $layersToProcess = [];

        // Bulk loading - carregar todos os segments de uma vez
        $segmentIds = array_filter(array_column($segments, 'id'));
        $existingSegments = Segment::query()
            ->whereIn('id', $segmentIds)
            ->get()
            ->keyBy('id');

        foreach ($segments as $segmentData) {
            $segmentId = data_get($segmentData, 'id');
            $segment = $existingSegments->get($segmentId);

            if (!$segment) {
                // Preparar novo segmento para batch insert
                $segmentId = (string) Str::ulid();
                $createdCount++;
            } else {
                $segmentId = $segment->id;
                $updatedCount++;
            }

            // Preparar dados para batch upsert
            $data = $this->filterSegmentAttributes($segmentData);
            $data['id'] = $segmentId;
            $data['shelf_id'] = $shelf->id;
            $data['tenant_id'] = $shelf->tenant_id;
            $data['user_id'] = $shelf->user_id;

            $segmentsToUpsert[] = $data;
            $processedSegmentIds[] = $segmentId;

            // Guardar layers para processar depois
            if (isset($segmentData['layer'])) {
                $layersToProcess[] = [
                    'segment_id' => $segmentId,
                    'layer_data' => $segmentData['layer'],
                ];
            }
        }

        // BATCH UPSERT - 1 query ao invés de N queries!
        if (!empty($segmentsToUpsert)) {
            Segment::upsert(
                $segmentsToUpsert,
                ['id'], // Unique identifier
                ['width', 'ordering', 'position', 'quantity', 'spacing', 'alignment', 'status', 'shelf_id'] // Campos para atualizar
            );
        }

        // Processar layers em batch
        if (!empty($layersToProcess)) {
            $this->processLayersBatch($layersToProcess);
        }

        // Identificar e remover segmentos órfãos
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
    private function filterSegmentAttributes(array $data): array
    {
        // Fix: Extrair status.value se vier como array
        $status = data_get($data, 'status', 'published');
        if (is_array($status)) {
            $status = $status['value'] ?? 'published';
        }

        $fillable = [
            'width' => data_get($data, 'width', 30),
            'ordering' => data_get($data, 'ordering', 0),
            'position' => data_get($data, 'position', 0),
            'quantity' => data_get($data, 'quantity', 1),
            'spacing' => data_get($data, 'spacing', 2),
            // 'settings' => data_get($data, 'settings', []),
            'alignment' => data_get($data, 'alignment', 'left'),
            'status' => $status,
        ];

        return $fillable;
    }


    /**
     * Processa múltiplas layers em batch para performance
     *
     * @param array $layersData Array de ['segment_id' => ..., 'layer_data' => ...]
     * @return void
     */
    private function processLayersBatch(array $layersData): void
    {
        if (empty($layersData)) {
            return;
        }

        // Buscar todas as layers existentes de uma vez
        $segmentIds = array_column($layersData, 'segment_id');
        $existingLayers = Layer::query()
            ->whereIn('segment_id', $segmentIds)
            ->get()
            ->keyBy('segment_id');

        // Buscar tenant_id e user_id dos segments de uma vez só
        $segments = Segment::query()->whereIn('id', $segmentIds)->get()->keyBy('id');

        $layersToUpsert = [];
        $createdCount = 0;
        $updatedCount = 0;

        foreach ($layersData as $item) {
            $segmentId = $item['segment_id'];
            $layerData = $item['layer_data'];

            $existingLayer = $existingLayers->get($segmentId);
            $layerId = $existingLayer ? $existingLayer->id : data_get($layerData, 'id');

            if (!$layerId) {
                $layerId = (string) Str::ulid();
                $createdCount++;
            } else {
                $updatedCount++;
            }

            // Preparar dados para batch upsert
            $data = $this->filterLayerAttributes($layerData);
            $data['id'] = $layerId;
            $data['segment_id'] = $segmentId;

            // Pegar tenant_id e user_id do segment
            $segment = $segments->get($segmentId);
            if ($segment) {
                $data['tenant_id'] = $segment->tenant_id;
                $data['user_id'] = $segment->user_id;
            }

            $layersToUpsert[] = $data;
        }

        // BATCH UPSERT - 1 query ao invés de N queries!
        if (!empty($layersToUpsert)) {
            Layer::upsert(
                $layersToUpsert,
                ['id'], // Unique identifier
                ['product_id', 'height', 'quantity', 'spacing', 'settings', 'alignment', 'status', 'segment_id'] // Campos para atualizar
            );
        }

        // Remover layers órfãs (relação 1:1)
        foreach ($segmentIds as $segmentId) {
            $validLayerId = collect($layersToUpsert)->firstWhere('segment_id', $segmentId)['id'] ?? null;
            if ($validLayerId) {
                Layer::query()
                    ->where('segment_id', $segmentId)
                    ->where('id', '!=', $validLayerId)
                    ->delete();
            }
        }
    }

    /**
     * Filtra atributos da camada (layer)
     *
     * @param array $data
     * @return array
     */
    private function filterLayerAttributes(array $data): array
    {
        $fillable = [
            'product_id',
            'height',
            'quantity',
            'spacing',
            'settings',
            'alignment',
            'status',
        ];

        $filtered = array_intersect_key($data, array_flip($fillable));

        // Fix: Se status vier como array {value, label, color}, extrair apenas o value
        if (isset($filtered['status']) && is_array($filtered['status'])) {
            $filtered['status'] = $filtered['status']['value'] ?? null;
        }

        return $filtered;
    }
}
