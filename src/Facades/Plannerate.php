<?php
/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */
namespace Callcocam\Plannerate\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Callcocam\Plannerate\Plannerate
 */
class Plannerate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Callcocam\Plannerate\Plannerate::class;
    }
}
