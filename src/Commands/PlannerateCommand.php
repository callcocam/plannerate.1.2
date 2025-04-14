<?php
/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */
namespace Callcocam\Plannerate\Commands;

use Illuminate\Console\Command;

class PlannerateCommand extends Command
{
    public $signature = 'plannerate';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
