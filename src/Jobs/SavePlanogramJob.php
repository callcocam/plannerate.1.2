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
     * Create a new job instance.
     */
    public function __construct(public $request, public Planogram $planogram, public $user = null)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
       PlannerateUpdateSevice::make($this->user)->update($this->request, $this->planogram);
    }
 
}
