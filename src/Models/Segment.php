<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Models;

use Callcocam\LaraGatekeeper\Core\Landlord\BelongsToTenants;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Segment extends Model
{
    use HasUlids, SoftDeletes, BelongsToTenants;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'shelf_id',
        'width',
        'distributed_width',
        'ordering',
        'position',
        'quantity', 
        'spacing',
        'settings',
        'alignment',
        'status',
    ];

    protected $casts = [
        'width' => 'decimal:2',
        'distributed_width' => 'decimal:2',
        'settings' => 'array',
    ];

    public function layer()
    {
        return $this->hasOne(Layer::class) ;
    }

    public function shelf()
    {
        return $this->belongsTo(Shelf::class);
    }

    /**
     * Boot method para recalcular larguras quando quantity mudar
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($segment) {
            // Recalcula larguras distribuídas quando quantity for alterada
            if ($segment->isDirty('quantity')) {
                // Carrega o relacionamento shelf se não estiver carregado
                if (!$segment->relationLoaded('shelf')) {
                    $segment->load('shelf');
                }
                
                // Recalcula se tiver o relacionamento necessário
                if ($segment->shelf) {
                    $segment->shelf->calculateDistributedWidths();
                }
            }
        });
    }
}
