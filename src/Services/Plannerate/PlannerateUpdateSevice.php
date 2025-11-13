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
use Illuminate\Support\Facades\Storage;
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
     * Atualiza o planograma completo com upserts diretos
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

            Storage::disk('local')->put('plannerate-debug-update.json', json_encode($data, JSON_PRETTY_PRINT));

            // Atualiza os atributos básicos do planograma
            $planogram->fill($this->filterPlanogramAttributes($data));
            $planogram->save();

            // Processa as gôndolas e sua estrutura aninhada
            $this->processGondolas($planogram, data_get($data, 'gondolas', []));

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erro ao atualizar planograma', [
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
     * Processa as gôndolas usando upsert direto
     *
     * @param mixed $planogram
     * @param array $gondolas
     * @return void
     */
    private function processGondolas($planogram, array $gondolas): void
    {
        foreach ($gondolas as $gondolaData) {
            // Preparar dados da gondola
            $data = $this->prepareGondolaData($gondolaData, $planogram);

            // Upsert direto - 1 query resolve criar OU atualizar
            Gondola::upsert([$data], ['id'], [
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
                'updated_at'
            ]);

            // Processar seções desta gôndola
            if (isset($gondolaData['sections'])) {
                $this->processSections($gondolaData['id'], $gondolaData['sections'], $planogram);
            }
        }

        Log::info('Gondolas processadas', ['count' => count($gondolas)]);
    }

    /**
     * Prepara dados da gôndola para upsert
     *
     * @param array $data
     * @param mixed $planogram
     * @return array
     */
    private function prepareGondolaData(array $data, $planogram): array
    {
        // Fix: Extrair status.value se vier como array
        $status = data_get($data, 'status', 'published');
        if (is_array($status)) {
            $status = $status['value'] ?? 'published';
        }

        return [
            'id' => data_get($data, 'id'),
            'tenant_id' => $planogram->tenant_id,
            'user_id' => $planogram->user_id,
            'planogram_id' => $planogram->id,
            'name' => data_get($data, 'name', 'Gôndola'),
            'slug' => data_get($data, 'slug'),
            'num_modulos' => data_get($data, 'num_modulos', 1), // NOT NULL DEFAULT 1
            'location' => data_get($data, 'location'),
            'side' => data_get($data, 'side'),
            'flow' => data_get($data, 'flow', 'left_to_right'), // NOT NULL DEFAULT 'left_to_right'
            'alignment' => data_get($data, 'alignment', 'justify'), // NOT NULL DEFAULT 'justify'
            'scale_factor' => data_get($data, 'scale_factor', 3), // NOT NULL DEFAULT 3
            'status' => $status, // NOT NULL DEFAULT 'draft'
            'linked_map_gondola_id' => data_get($data, 'linked_map_gondola_id'),
            'linked_map_gondola_category' => data_get($data, 'linked_map_gondola_category'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Processa as seções usando upsert direto
     *
     * @param string $gondolaId
     * @param array $sections
     * @param mixed $planogram
     * @return void
     */
    private function processSections(string $gondolaId, array $sections, $planogram): void
    {
        $shelfService = new ShelfPositioningService();

        foreach ($sections as $i => $sectionData) {
            // Preparar dados da section
            $data = $this->prepareSectionData($sectionData, $gondolaId, $planogram, $shelfService, $i);

            // Upsert direto - 1 query resolve criar OU atualizar
            Section::upsert([$data], ['id'], [
                'name',
                'code',
                'slug',
                'width',
                'height',
                'num_shelves',
                'base_height',
                'base_depth',
                'base_width',
                'hole_height',
                'hole_width',
                'hole_spacing',
                'cremalheira_width',
                'ordering',
                'alignment',
                'settings',
                'status',
                'deleted_at',
                'updated_at'
            ]);

            // Processar prateleiras desta seção
            if (isset($sectionData['shelves'])) {
                $this->processShelves($sectionData['id'], $sectionData['shelves'], $planogram);
            }
        }
    }

    /**
     * Prepara dados da seção para upsert
     *
     * @param array $data
     * @param string $gondolaId
     * @param mixed $planogram
     * @param ShelfPositioningService $shelfService
     * @param int $index
     * @return array
     */
    private function prepareSectionData(array $data, string $gondolaId, $planogram, ShelfPositioningService $shelfService, int $index): array
    {
        // Fix: Extrair status.value se vier como array
        $status = data_get($data, 'status', 'draft');
        if (is_array($status)) {
            $status = $status['value'] ?? 'draft';
        }

        // Fix: Extrair alignment.value se vier como array
        $alignment = data_get($data, 'alignment');
        if (is_array($alignment)) {
            $alignment = $alignment['value'] ?? null;
        }

        // Fix: Converter deleted_at do formato ISO 8601 para formato MySQL
        $deletedAt = data_get($data, 'deleted_at', null);
        if ($deletedAt && is_string($deletedAt)) {
            try {
                $deletedAt = \Carbon\Carbon::parse($deletedAt)->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $deletedAt = null;
            }
        }

        $fillable = [
            'id' => data_get($data, 'id'),
            'tenant_id' => $planogram->tenant_id,
            'user_id' => $planogram->user_id,
            'gondola_id' => $gondolaId,
            'name' => sprintf('%d# Sessão', $index),
            'code' => data_get($data, 'code'), // varchar nullable
            'slug' => data_get($data, 'slug', Str::slug(data_get($data, 'name', 'seccao'))),
            'width' => data_get($data, 'width', 130),
            'height' => data_get($data, 'height', 180),
            'num_shelves' => data_get($data, 'num_shelves', 4),
            'base_height' => data_get($data, 'base_height', 17), // NOT NULL DEFAULT 17
            'base_depth' => data_get($data, 'base_depth', 40), // NOT NULL DEFAULT 40
            'base_width' => data_get($data, 'base_width', 130), // NOT NULL DEFAULT 130
            'cremalheira_width' => data_get($data, 'cremalheira_width', 4.00), // NOT NULL DEFAULT 4.00
            'hole_height' => data_get($data, 'hole_height', 2.00), // NOT NULL DEFAULT 2.00
            'hole_width' => data_get($data, 'hole_width', 2.00), // NOT NULL DEFAULT 2.00
            'hole_spacing' => data_get($data, 'hole_spacing', 2.00), // NOT NULL DEFAULT 2.00
            'ordering' => data_get($data, 'ordering', 0), // NOT NULL DEFAULT 0
            'alignment' => $alignment, // nullable
            'status' => $status, // NOT NULL DEFAULT 'draft'
            'deleted_at' => $deletedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Calcular furos e adicionar às configurações
        $sectionSettings = data_get($data, 'settings', []);
        $sectionSettings['holes'] = $shelfService->calculateHoles($fillable);
        $fillable['settings'] = $sectionSettings;

        // FIX: Serializar settings para JSON (upsert não aplica casts automaticamente)
        if (isset($fillable['settings']) && is_array($fillable['settings'])) {
            $fillable['settings'] = json_encode($fillable['settings']);
        }

        return $fillable;
    }

    /**
     * Processa as prateleiras usando upsert direto
     *
     * @param string $sectionId
     * @param array $shelves
     * @param mixed $planogram
     * @return void
     */
    private function processShelves(string $sectionId, array $shelves, $planogram): void
    {
        foreach ($shelves as $shelfData) {
            // Preparar dados da shelf
            $data = $this->prepareShelfData($shelfData, $sectionId, $planogram);

            // Upsert direto com Eloquent - aplica casts automaticamente
            Shelf::upsert([$data], ['id'], [
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
                'deleted_at',
                'updated_at',
            ]);

            // Processar segmentos desta prateleira
            if (isset($shelfData['segments'])) {
                $this->processSegments($shelfData['id'], $shelfData['segments'], $planogram);
            }
        }
    }

    /**
     * Prepara dados da prateleira para upsert
     *
     * @param array $data
     * @param string $sectionId
     * @param mixed $planogram
     * @return array
     */
    private function prepareShelfData(array $data, string $sectionId, $planogram): array
    {
        // Fix: Extrair status.value se vier como array
        $status = data_get($data, 'status', 'published');
        if (is_array($status)) {
            $status = $status['value'] ?? 'published';
        }

        // Fix: Extrair alignment.value se vier como array
        $alignment = data_get($data, 'alignment');
        if (is_array($alignment)) {
            $alignment = $alignment['value'] ?? null;
        }

        // Fix: Converter deleted_at do formato ISO 8601 para formato MySQL
        $deletedAt = data_get($data, 'deleted_at', null);
        if ($deletedAt && is_string($deletedAt)) {
            try {
                $deletedAt = \Carbon\Carbon::parse($deletedAt)->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $deletedAt = null;
            }
        }

        $fillable = [
            'id' => data_get($data, 'id'),
            'tenant_id' => $planogram->tenant_id,
            'user_id' => $planogram->user_id,
            'section_id' => $sectionId,
            'code' => data_get($data, 'code'), // varchar nullable
            'product_type' => data_get($data, 'product_type', 'normal'), // NOT NULL DEFAULT 'normal'
            'shelf_width' => data_get($data, 'shelf_width', 4), // NOT NULL DEFAULT 4
            'shelf_height' => data_get($data, 'shelf_height', 4), // NOT NULL DEFAULT 4
            'shelf_depth' => data_get($data, 'shelf_depth', 40), // NOT NULL DEFAULT 40
            'shelf_position' => data_get($data, 'shelf_position', 0), // NOT NULL DEFAULT 0
            'ordering' => data_get($data, 'ordering', 0), // NOT NULL DEFAULT 0
            'alignment' => $alignment, // nullable
            'spacing' => data_get($data, 'spacing', 0), // NOT NULL DEFAULT 0
            'settings' => data_get($data, 'settings', []),
            'status' => $status, // NOT NULL DEFAULT 'draft'
            'deleted_at' => $deletedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // FIX: Serializar settings para JSON (upsert não aplica casts automaticamente)
        if (isset($fillable['settings']) && is_array($fillable['settings'])) {
            $fillable['settings'] = json_encode($fillable['settings']);
        }

        return $fillable;
    }

    /**
     * Processa os segmentos em batch upsert
     *
     * @param string $shelfId
     * @param array $segments
     * @param mixed $planogram
     * @return void
     */
    private function processSegments(string $shelfId, array $segments, $planogram): void
    {
        $segmentsToUpsert = [];
        $layersToProcess = [];

        foreach ($segments as $segmentData) {
            // Preparar dados do segment
            $data = $this->prepareSegmentData($segmentData, $shelfId, $planogram);
            $segmentsToUpsert[] = $data;

            // Guardar layers para processar depois
            if (isset($segmentData['layer'])) {
                $layersToProcess[] = [
                    'segment_id' => $data['id'],
                    'layer_data' => $segmentData['layer'],
                    'tenant_id' => $planogram->tenant_id,
                    'user_id' => $planogram->user_id,
                ];
            }
        }

        // BATCH UPSERT - 1 query ao invés de N queries
        if (!empty($segmentsToUpsert)) {
            Segment::upsert(
                $segmentsToUpsert,
                ['id'],
                ['width', 'distributed_width', 'height', 'ordering', 'alignment', 'position', 'quantity', 'spacing', 'settings', 'status', 'shelf_id', 'deleted_at', 'updated_at']
            );
        }

        // Processar layers em batch
        if (!empty($layersToProcess)) {
            $this->processLayersBatch($layersToProcess);
        }
    }

    /**
     * Prepara dados do segmento para upsert
     *
     * @param array $data
     * @param string $shelfId
     * @param mixed $planogram
     * @return array
     */
    private function prepareSegmentData(array $data, string $shelfId, $planogram): array
    {
        // Fix: Extrair status.value se vier como array
        $status = data_get($data, 'status', 'published');
        if (is_array($status)) {
            $status = $status['value'] ?? 'published';
        }

        // Fix: Extrair alignment.value se vier como array
        $alignment = data_get($data, 'alignment');
        if (is_array($alignment)) {
            $alignment = $alignment['value'] ?? null;
        }

        // Fix: Converter deleted_at do formato ISO 8601 para formato MySQL
        $deletedAt = data_get($data, 'deleted_at', null);
        if ($deletedAt && is_string($deletedAt)) {
            try {
                $deletedAt = \Carbon\Carbon::parse($deletedAt)->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $deletedAt = null;
            }
        }

        $fillable = [
            'id' => data_get($data, 'id'),
            'tenant_id' => $planogram->tenant_id,
            'user_id' => $planogram->user_id,
            'shelf_id' => $shelfId,
            'width' => data_get($data, 'width'),
            'distributed_width' => data_get($data, 'distributed_width'), // decimal nullable
            'height' => data_get($data, 'height'),
            'ordering' => data_get($data, 'ordering', 0), // NOT NULL DEFAULT 0
            'alignment' => $alignment, // nullable
            'position' => data_get($data, 'position'),
            'quantity' => data_get($data, 'quantity', 1), // NOT NULL DEFAULT 1
            'spacing' => data_get($data, 'spacing'),
            'settings' => data_get($data, 'settings'), // json nullable
            'status' => $status, // NOT NULL DEFAULT 'draft'
            'deleted_at' => $deletedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // FIX: Serializar settings para JSON (upsert não aplica casts automaticamente)
        if (isset($fillable['settings']) && is_array($fillable['settings'])) {
            $fillable['settings'] = json_encode($fillable['settings']);
        }

        return $fillable;
    }

    /**
     * Processa múltiplas layers em batch para performance
     *
     * @param array $layersData Array de ['segment_id' => ..., 'layer_data' => ..., 'tenant_id' => ..., 'user_id' => ...]
     * @return void
     */
    private function processLayersBatch(array $layersData): void
    {
        if (empty($layersData)) {
            return;
        }

        $layersToUpsert = [];

        foreach ($layersData as $item) {
            // Preparar dados da layer (tenant_id/user_id já vêm do parent)
            $data = $this->prepareLayerData(
                $item['layer_data'],
                $item['segment_id'],
                $item['tenant_id'],
                $item['user_id']
            );

            $layersToUpsert[] = $data;
        }

        // BATCH UPSERT - 1 query ao invés de N queries
        if (!empty($layersToUpsert)) {
            Layer::upsert(
                $layersToUpsert,
                ['id'],
                ['product_id', 'height', 'distributed_width', 'quantity', 'alignment', 'spacing', 'settings', 'status', 'segment_id', 'updated_at']
            );
        }

        // Remover layers órfãs (relação 1:1)
        $segmentIds = array_column($layersData, 'segment_id');
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
     * Prepara dados da camada (layer) para upsert
     *
     * @param array $data
     * @param string $segmentId
     * @param string $tenantId
     * @param string $userId
     * @return array
     */
    private function prepareLayerData(array $data, string $segmentId, string $tenantId, string $userId): array
    {
        // Fix: Extrair status.value se vier como array
        $status = data_get($data, 'status', 'published');
        if (is_array($status)) {
            $status = $status['value'] ?? 'published';
        }

        // Fix: Extrair alignment.value se vier como array
        $alignment = data_get($data, 'alignment');
        if (is_array($alignment)) {
            $alignment = $alignment['value'] ?? null;
        }

        $fillable = [
            'id' => data_get($data, 'id'),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'segment_id' => $segmentId,
            'product_id' => data_get($data, 'product_id'),
            'height' => data_get($data, 'height'),
            'distributed_width' => data_get($data, 'distributed_width'), // decimal nullable
            'quantity' => data_get($data, 'quantity', 1), // NOT NULL DEFAULT 1
            'alignment' => $alignment, // nullable
            'spacing' => data_get($data, 'spacing', 0), // NOT NULL DEFAULT 0
            'settings' => data_get($data, 'settings'), // json nullable
            'status' => $status, // NOT NULL DEFAULT 'draft'
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // FIX: Serializar settings para JSON (upsert não aplica casts automaticamente)
        if (isset($fillable['settings']) && is_array($fillable['settings'])) {
            $fillable['settings'] = json_encode($fillable['settings']);
        }

        return $fillable;
    }
}
