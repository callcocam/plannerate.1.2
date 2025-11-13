<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Jobs;

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
        if ($this->user) {
            $this->onQueue(str_replace('', '-', $this->user->slug));
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        PlannerateUpdateSevice::make($this->user)->update($this->request, $this->planogram);
    }
}
