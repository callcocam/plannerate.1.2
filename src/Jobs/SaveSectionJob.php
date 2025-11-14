<?php

namespace Callcocam\Plannerate\Jobs;

use App\Events\QueueActivityUpdated;
use Callcocam\Plannerate\Models\Gondola;
use Callcocam\Plannerate\Models\Section;
use Callcocam\Plannerate\Services\ShelfPositioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SaveSectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $sectionData,
        public string $gondolaId,
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
            // Broadcast: processando
            QueueActivityUpdated::dispatch(
                'SaveSectionJob',
                'planogramas',
                'processing',
                [
                    'section_id' => data_get($this->sectionData, 'id'),
                    'gondola_id' => $this->gondolaId
                ]
            );

            $gondola = Gondola::findOrFail($this->gondolaId);
            
            $sectionId = data_get($this->sectionData, 'id', (string) Str::ulid());
            $section = Section::find($sectionId);

            if (!$section) {
                // Usar insertOrIgnore para evitar duplicação
                $inserted = DB::table('sections')->insertOrIgnore([
                    'id' => $sectionId,
                    'tenant_id' => $gondola->tenant_id,
                    'user_id' => $gondola->user_id,
                    'gondola_id' => $gondola->id,
                    'name' => data_get($this->sectionData, 'name', 'Seção'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $section = Section::find($sectionId);
                
                if (!$section) {
                    $section = Section::firstOrCreate(
                        ['id' => $sectionId],
                        [
                            'tenant_id' => $gondola->tenant_id,
                            'user_id' => $gondola->user_id,
                            'gondola_id' => $gondola->id,
                            'name' => data_get($this->sectionData, 'name', 'Seção'),
                        ]
                    );
                }
            }

            // Atualizar atributos da seção
            $shelfService = new ShelfPositioningService();
            $data = $this->filterSectionAttributes($this->sectionData, $shelfService);
            $data['gondola_id'] = $gondola->id;
            
            $section->timestamps = false;
            $section->update($data);

            Log::info('✅ [SECTION] Seção salva', [
                'section_id' => $section->id,
                'gondola_id' => $this->gondolaId,
            ]);

            DB::commit();

            // Broadcast: concluído
            QueueActivityUpdated::dispatch(
                'SaveSectionJob',
                'planogramas',
                'completed',
                [
                    'section_id' => $section->id,
                    'gondola_id' => $this->gondolaId
                ]
            );

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ [SECTION] Erro ao salvar seção', [
                'section_id' => data_get($this->sectionData, 'id'),
                'gondola_id' => $this->gondolaId,
                'exception' => $e->getMessage(),
            ]);

            // Broadcast: falhou
            QueueActivityUpdated::dispatch(
                'SaveSectionJob',
                'planogramas',
                'failed',
                [
                    'section_id' => data_get($this->sectionData, 'id'),
                    'gondola_id' => $this->gondolaId,
                    'error' => $e->getMessage()
                ]
            );

            throw $e;
        }
    }

    /**
     * Filtra atributos da seção
     */
    private function filterSectionAttributes(array $data, ShelfPositioningService $shelfService): array
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
            'deleted_at' => $deletedAt,
        ];

        // Calcular furos e adicionar às configurações
        $sectionSettings = data_get($data, 'settings', []);
        $sectionSettings['holes'] = $shelfService->calculateHoles($fillable);
        $fillable['settings'] = $sectionSettings;

        return $fillable;
    }
}
