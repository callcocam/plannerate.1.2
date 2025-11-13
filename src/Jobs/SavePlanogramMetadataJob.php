<?php

namespace Callcocam\Plannerate\Jobs;

use Callcocam\Plannerate\Models\Planogram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SavePlanogramMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $data,
        public string $planogramId,
        public $user
    ) {
        $this->onQueue('planogramas');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $planogram = Planogram::findOrFail($this->planogramId);

            // Atualizar apenas os metadados do planograma
            $planogram->fill($this->filterPlanogramAttributes($this->data));
            $planogram->save();

            Log::info('✅ [METADATA] Metadados do planograma atualizados', [
                'planogram_id' => $planogram->id,
                'name' => $planogram->name,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [METADATA] Erro ao atualizar metadados do planograma', [
                'planogram_id' => $this->planogramId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Filtra apenas os atributos pertinentes ao modelo Planogram
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
}
