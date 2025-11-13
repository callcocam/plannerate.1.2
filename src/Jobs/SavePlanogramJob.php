<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Jobs;

use App\Events\QueueActivityUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;


use Callcocam\Plannerate\Models\Planogram;
use Callcocam\Plannerate\Services\Plannerate\PlannerateUpdateSevice;


class SavePlanogramJob implements ShouldQueue
{
    use Queueable;

    /**
     * Número de tentativas em caso de falha
     */
    public $tries = 3;

    /**
     * Número máximo de exceções não tratadas permitidas
     */
    public $maxExceptions = 3;

    /**
     * Tempo de espera entre tentativas (em segundos)
     */
    public $backoff = [1, 5, 10];

    /**
     * Create a new job instance.
     */
    public function __construct(public $request, public Planogram $planogram, public $user = null)
    {
        // Garante que jobs do mesmo planograma processem sequencialmente
        // Isso previne deadlocks ao evitar múltiplos jobs modificando os mesmos registros
        $this->onQueue('planogramas');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Notificar que o job começou a processar
        broadcast(new QueueActivityUpdated(
            jobName: 'SavePlanogramJob',
            queueName: 'planogramas',
            status: 'processing',
            metadata: [
                'planogram_id' => $this->planogram->id,
                'planogram_name' => $this->planogram->name,
                'started_at' => now()->toISOString(),
            ],
            tenantId: $this->planogram->tenant_id ?? null
        ));

        try {
            // Processar a atualização do planograma
            PlannerateUpdateSevice::make($this->user)->update($this->request, $this->planogram);

            // Notificar sucesso
            broadcast(new QueueActivityUpdated(
                jobName: 'SavePlanogramJob',
                queueName: 'planogramas',
                status: 'completed',
                metadata: [
                    'planogram_id' => $this->planogram->id,
                    'planogram_name' => $this->planogram->name,
                    'completed_at' => now()->toISOString(),
                ],
                tenantId: $this->planogram->tenant_id ?? null
            ));
        } catch (\Exception $e) {
            // Notificar erro
            broadcast(new QueueActivityUpdated(
                jobName: 'SavePlanogramJob',
                queueName: 'planogramas',
                status: 'failed',
                metadata: [
                    'planogram_id' => $this->planogram->id,
                    'planogram_name' => $this->planogram->name,
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString(),
                ],
                tenantId: $this->planogram->tenant_id ?? null
            ));

            // Re-lançar a exceção para que o Laravel trate a falha do job
            throw $e;
        }
    }
}
