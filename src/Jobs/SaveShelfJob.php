<?php

namespace Callcocam\Plannerate\Jobs;

use Callcocam\Plannerate\Models\Layer;
use Callcocam\Plannerate\Models\Section;
use Callcocam\Plannerate\Models\Segment;
use Callcocam\Plannerate\Models\Shelf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SaveShelfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $shelfData,
        public string $sectionId,
        public $user
    ) {
        $this->onQueue('planogramas');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::beginTransaction();

        try {
            $section = Section::findOrFail($this->sectionId);
            
            $shelfId = data_get($this->shelfData, 'id', (string) Str::ulid());
            $shelf = Shelf::find($shelfId);

            if (!$shelf) {
                // Usar insertOrIgnore para evitar duplicação
                $inserted = DB::table('shelves')->insertOrIgnore([
                    'id' => $shelfId,
                    'tenant_id' => $section->tenant_id,
                    'user_id' => $section->user_id,
                    'section_id' => $section->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $shelf = Shelf::find($shelfId);
                
                if (!$shelf) {
                    $shelf = Shelf::firstOrCreate(
                        ['id' => $shelfId],
                        [
                            'tenant_id' => $section->tenant_id,
                            'user_id' => $section->user_id,
                            'section_id' => $section->id,
                        ]
                    );
                }
            }

            // Atualizar atributos da prateleira
            $data = $this->filterShelfAttributes($this->shelfData);
            $data['section_id'] = $section->id;
            
            $shelf->timestamps = false;
            $shelf->update($data);

            // Processar segmentos em batch
            $this->processSegments($shelf, data_get($this->shelfData, 'segments', []));

            Log::info('✅ [SHELF] Prateleira salva', [
                'shelf_id' => $shelf->id,
                'section_id' => $this->sectionId,
                'segments_count' => count(data_get($this->shelfData, 'segments', [])),
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ [SHELF] Erro ao salvar prateleira', [
                'shelf_id' => data_get($this->shelfData, 'id'),
                'section_id' => $this->sectionId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Filtra atributos da prateleira
     */
    private function filterShelfAttributes(array $data): array
    {
        // Fix: Converter deleted_at do formato ISO 8601 para formato MySQL
        $deletedAt = data_get($data, 'deleted_at', null);
        if ($deletedAt && is_string($deletedAt)) {
            try {
                $deletedAt = \Carbon\Carbon::parse($deletedAt)->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $deletedAt = null;
            }
        }

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
            'ordering' => data_get($data, 'ordering', 0),
            'spacing' => data_get($data, 'spacing', 2),
            'settings' => data_get($data, 'settings', []),
            'status' => $status,
            'alignment' => data_get($data, 'alignment', 'left'),
            'deleted_at' => $deletedAt,
        ];

        return $fillable;
    }

    /**
     * Processa segmentos em batch
     */
    private function processSegments(Shelf $shelf, array $segments): void
    {
        if (empty($segments)) {
            return;
        }

        $segmentsToUpsert = [];
        $layersToProcess = [];

        foreach ($segments as $segmentData) {
            $segmentId = data_get($segmentData, 'id', (string) Str::ulid());
            
            // Preparar dados para batch upsert
            $data = $this->filterSegmentAttributes($segmentData);
            $data['id'] = substr($segmentId, 0, 27);
            $data['shelf_id'] = $shelf->id;
            $data['tenant_id'] = $shelf->tenant_id;
            $data['user_id'] = $shelf->user_id;

            $segmentsToUpsert[] = $data;

            // Guardar layers para processar depois
            if (isset($segmentData['layer'])) {
                $layersToProcess[] = [
                    'segment_id' => $segmentId,
                    'layer_data' => $segmentData['layer'],
                    'tenant_id' => $shelf->tenant_id,
                    'user_id' => $shelf->user_id,
                ];
            }
        }

        // BATCH UPSERT - 1 query ao invés de N queries!
        if (!empty($segmentsToUpsert)) {
            Segment::upsert(
                $segmentsToUpsert,
                ['id'],
                ['width', 'ordering', 'position', 'quantity', 'spacing', 'alignment', 'status', 'shelf_id', 'deleted_at', 'updated_at']
            );
        }

        // Processar layers em batch
        if (!empty($layersToProcess)) {
            $this->processLayersBatch($layersToProcess);
        }
    }

    /**
     * Filtra atributos do segmento
     */
    private function filterSegmentAttributes(array $data): array
    {
        // Fix: Converter deleted_at
        $deletedAt = data_get($data, 'deleted_at', null);
        if ($deletedAt && is_string($deletedAt)) {
            try {
                $deletedAt = \Carbon\Carbon::parse($deletedAt)->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $deletedAt = null;
            }
        }

        $status = data_get($data, 'status', 'published');
        if (is_array($status)) {
            $status = $status['value'] ?? 'published';
        }

        return [
            'width' => data_get($data, 'width', 30),
            'ordering' => data_get($data, 'ordering', 0),
            'position' => data_get($data, 'position', 0),
            'quantity' => data_get($data, 'quantity', 1),
            'spacing' => data_get($data, 'spacing', 2),
            'deleted_at' => $deletedAt,
            'alignment' => data_get($data, 'alignment', 'left'),
            'status' => $status,
            'updated_at' => now(),
        ];
    }

    /**
     * Processa múltiplas layers em batch
     */
    private function processLayersBatch(array $layersData): void
    {
        $layersToUpsert = [];

        foreach ($layersData as $item) {
            $segmentId = $item['segment_id'];
            $layerData = $item['layer_data'];
            $layerId = data_get($layerData, 'id', (string) Str::ulid());

            $data = $this->filterLayerAttributes($layerData);
            $data['id'] = substr($layerId, 0, 27);
            $data['segment_id'] = $segmentId;
            $data['tenant_id'] = $item['tenant_id'];
            $data['user_id'] = $item['user_id'];
            $data['created_at'] = now();
            $data['updated_at'] = now();

            $layersToUpsert[] = $data;
        }

        if (!empty($layersToUpsert)) {
            Layer::upsert(
                $layersToUpsert,
                ['id'],
                ['product_id', 'height', 'quantity', 'spacing', 'settings', 'alignment', 'status', 'segment_id', 'updated_at']
            );
        }
    }

    /**
     * Filtra atributos da camada (layer)
     */
    private function filterLayerAttributes(array $data): array
    {
        $status = data_get($data, 'status', 'published');
        if (is_array($status)) {
            $status = $status['value'] ?? 'published';
        }

        return [
            'product_id' => data_get($data, 'product_id'),
            'height' => data_get($data, 'height'),
            'quantity' => data_get($data, 'quantity'),
            'spacing' => data_get($data, 'spacing', 0),
            'settings' => data_get($data, 'settings'),
            'alignment' => data_get($data, 'alignment'),
            'status' => $status,
        ];
    }
}
