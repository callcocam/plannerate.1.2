<?php

namespace Callcocam\Plannerate\Jobs;

use Callcocam\Plannerate\Models\Gondola;
use Callcocam\Plannerate\Models\Planogram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SaveGondolaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $gondolaData,
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
        DB::beginTransaction();

        try {
            $planogram = Planogram::findOrFail($this->planogramId);
            
            $gondolaId = data_get($this->gondolaData, 'id', (string) Str::ulid());
            $gondola = Gondola::find($gondolaId);

            if (!$gondola) {
                $gondola = new Gondola();
                $gondola->id = $gondolaId;
                $gondola->tenant_id = $planogram->tenant_id;
                $gondola->user_id = $planogram->user_id;
                $gondola->planogram_id = $planogram->id;
            }

            // Atualizar atributos da gôndola
            $gondola->fill($this->filterGondolaAttributes($this->gondolaData));
            $gondola->save();

            Log::info('✅ [GONDOLA] Gôndola salva', [
                'gondola_id' => $gondola->id,
                'planogram_id' => $this->planogramId,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ [GONDOLA] Erro ao salvar gôndola', [
                'gondola_id' => data_get($this->gondolaData, 'id'),
                'planogram_id' => $this->planogramId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Filtra atributos da gôndola
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
}
