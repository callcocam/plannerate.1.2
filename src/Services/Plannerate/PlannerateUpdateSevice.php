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
    /**
     * Factory method para criar uma instância do serviço
     */
    public static function make(): PlannerateUpdateSevice
    {
        return new static();
    }

    /**
     * Atualiza o planograma completo com comparação e limpeza de órfãos
     *
     * @param Request $request
     * @param mixed $planogram (App\Models\Planogram ou Callcocam\Plannerate\Models\Planogram)
     * @return void
     * @throws \Exception
     */
    public function update(Request $request, $planogram): void
    {
        DB::beginTransaction();

        try {
            $data = $request->all();

            Log::info('🔄 [UPDATE SERVICE] Iniciando atualização do planograma', [
                'planogram_id' => $planogram->id,
                'planogram_name' => $planogram->name,
                'gondolas_count' => count(data_get($data, 'gondolas', [])),
            ]);

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

        Log::info('🔍 [GONDOLAS] Comparando gôndolas', [
            'existing_count' => count($existingGondolaIds),
            'incoming_count' => count($gondolas),
        ]);

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
                $gondola->id = (string) Str::orderedUuid();
                $gondola->tenant_id = $planogram->tenant_id;
                $gondola->user_id = $planogram->user_id;
                $gondola->planogram_id = $planogram->id;

                Log::info('➕ [GONDOLA] Criando nova gôndola', [
                    'gondola_id' => $gondola->id,
                    'name' => data_get($gondolaData, 'name'),
                ]);
            } else {
                Log::info('🔄 [GONDOLA] Atualizando gôndola existente', [
                    'gondola_id' => $gondola->id,
                    'name' => data_get($gondolaData, 'name'),
                ]);
            }

            // Atualizar atributos da gôndola
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
                'orphan_ids' => $gondolasToDelete,
            ]);

            // Usar soft delete se disponível, caso contrário delete permanente
            Gondola::whereIn('id', $gondolasToDelete)->delete();
        }
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

        Log::info('🔍 [SECTIONS] Comparando seções', [
            'gondola_id' => $gondola->id,
            'existing_count' => count($existingSectionIds),
            'incoming_count' => count($sections),
        ]);

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
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $gondola->tenant_id,
                    'user_id' => $gondola->user_id,
                    'gondola_id' => $gondola->id,
                    'name' => data_get($sectionData, 'name', "Seção #{$i}"),
                ]);

                Log::info('➕ [SECTION] Criando nova seção', [
                    'section_id' => $section->id,
                    'gondola_id' => $gondola->id,
                    'name' => $section->name,
                ]);
            } else {
                Log::info('🔄 [SECTION] Atualizando seção existente', [
                    'section_id' => $section->id,
                    'gondola_id' => $gondola->id,
                ]);
            }

            // Atualizar atributos da seção
            $data = $this->filterSectionAttributes($sectionData, $shelfService, $gondola);
            $data['gondola_id'] = $gondola->id;
            $data['name'] = sprintf('%d# Sessão', $i);
            $section->update($data);

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
            Log::warning('🗑️ [SECTIONS] Removendo seções órfãs', [
                'gondola_id' => $gondola->id,
                'orphan_count' => count($sectionsToDelete),
                'orphan_ids' => $sectionsToDelete,
            ]);

            Section::whereIn('id', $sectionsToDelete)->delete();
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

        Log::info('🔍 [SHELVES] Comparando prateleiras', [
            'section_id' => $section->id,
            'existing_count' => count($existingShelfIds),
            'incoming_count' => count($shelves),
        ]);

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
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $section->tenant_id,
                    'user_id' => $section->user_id,
                    'section_id' => $section->id,
                ]);

                Log::info('➕ [SHELF] Criando nova prateleira', [
                    'shelf_id' => $shelf->id,
                    'section_id' => $section->id,
                    'index' => $i,
                ]);
            } else {
                Log::info('🔄 [SHELF] Atualizando prateleira existente', [
                    'shelf_id' => $shelf->id,
                    'section_id' => $section->id,
                ]);
            }

            // Atualizar atributos da prateleira
            $data = $this->filterShelfAttributes($shelfData, $shelfService, $i, $section);
            $data['section_id'] = $section->id;
            $shelf->update($data);

            // Registrar ID processado
            $processedShelfIds[] = $shelf->id;

            // Processar segmentos desta prateleira
            if (isset($shelfData['segments'])) {
                $this->processSegments($shelf, data_get($shelfData, 'segments', []));
            }
        }

        // Identificar e remover prateleiras órfãs
        $shelvesToDelete = array_diff($existingShelfIds, $processedShelfIds);

        if (!empty($shelvesToDelete)) {
            Log::warning('🗑️ [SHELVES] Removendo prateleiras órfãs', [
                'section_id' => $section->id,
                'orphan_count' => count($shelvesToDelete),
                'orphan_ids' => $shelvesToDelete,
            ]);

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
            'shelf_x_position' => data_get($data, 'shelf_x_position', 0),
            'quantity' => data_get($data, 'quantity', 1),
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

        Log::info('🔍 [SEGMENTS] Comparando segmentos', [
            'shelf_id' => $shelf->id,
            'existing_count' => count($existingSegmentIds),
            'incoming_count' => count($segments),
        ]);

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
                // Criar novo segmento
                $segment = Segment::query()->create([
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $shelf->tenant_id,
                    'user_id' => $shelf->user_id,
                    'shelf_id' => $shelf->id,
                ]);

                Log::info('➕ [SEGMENT] Criando novo segmento', [
                    'segment_id' => $segment->id,
                    'shelf_id' => $shelf->id,
                ]);
            } else {
                Log::info('🔄 [SEGMENT] Atualizando segmento existente', [
                    'segment_id' => $segment->id,
                    'shelf_id' => $shelf->id,
                ]);
            }

            // Atualizar atributos do segmento
            $data = $this->filterSegmentAttributes($segmentData);
            $data['shelf_id'] = $shelf->id;
            $segment->update($data);

            // Registrar ID processado
            $processedSegmentIds[] = $segment->id;

            // Processar camada (layer) deste segmento
            if (isset($segmentData['layer'])) {
                $this->processLayer($segment, data_get($segmentData, 'layer', []));
            }
        }

        // Identificar e remover segmentos órfãos
        $segmentsToDelete = array_diff($existingSegmentIds, $processedSegmentIds);

        if (!empty($segmentsToDelete)) {
            Log::warning('🗑️ [SEGMENTS] Removendo segmentos órfãos', [
                'shelf_id' => $shelf->id,
                'orphan_count' => count($segmentsToDelete),
                'orphan_ids' => $segmentsToDelete,
            ]);

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
            'settings' => data_get($data, 'settings', []),
            'alignment' => data_get($data, 'alignment', 'left'),
            'status' => $status,
            'tabindex' => data_get($data, 'tabindex', 0),
        ];

        return $fillable;
    }

    /**
     * Processa a camada (layer) de um segmento
     * Remove layers órfãs e gerencia a relação 1:1
     *
     * @param Segment $segment
     * @param array $layerData
     * @return void
     */
    private function processLayer(Segment $segment, array $layerData): void
    {
        $layerId = data_get($layerData, 'id');
        $layer = null;

        // Verificar se a layer existe
        if ($layerId) {
            $layer = Layer::query()->where('id', $layerId)->first();
        }

        // Se não existe, tentar buscar pela relação com o segmento
        if (!$layer) {
            $layer = Layer::query()->where('segment_id', $segment->id)->first();
        }

        if (!$layer) {
            // Criar nova layer
            $layer = Layer::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $segment->tenant_id,
                'user_id' => $segment->user_id,
                'segment_id' => $segment->id,
            ]);

            Log::info('➕ [LAYER] Criando nova layer', [
                'layer_id' => $layer->id,
                'segment_id' => $segment->id,
            ]);
        } else {
            Log::info('🔄 [LAYER] Atualizando layer existente', [
                'layer_id' => $layer->id,
                'segment_id' => $segment->id,
            ]);
        }

        // Atualizar atributos da camada
        $layer->fill($this->filterLayerAttributes($layerData));
        $layer->segment_id = $segment->id;
        $layer->save();

        // IMPORTANTE: Como a relação é 1:1, se houver outras layers órfãs
        // vinculadas a este segmento (não deveria acontecer), removê-las
        $orphanLayers = Layer::query()
            ->where('segment_id', $segment->id)
            ->where('id', '!=', $layer->id)
            ->get();

        if ($orphanLayers->isNotEmpty()) {
            Log::warning('🗑️ [LAYERS] Removendo layers órfãs duplicadas (relação 1:1)', [
                'segment_id' => $segment->id,
                'orphan_count' => $orphanLayers->count(),
                'orphan_ids' => $orphanLayers->pluck('id')->toArray(),
            ]);

            $orphanLayers->each->delete();
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
            'reload',
            'status',
            'tabindex',
        ];

        $filtered = array_intersect_key($data, array_flip($fillable));

        // Fix: Se status vier como array {value, label, color}, extrair apenas o value
        if (isset($filtered['status']) && is_array($filtered['status'])) {
            $filtered['status'] = $filtered['status']['value'] ?? null;
        }

        return $filtered;
    }
}
