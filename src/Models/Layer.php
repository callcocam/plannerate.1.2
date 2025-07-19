<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Models;

use App\Models\Product;
use Callcocam\LaraGatekeeper\Core\Landlord\BelongsToTenants;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Layer extends Model
{
    use HasUlids, SoftDeletes, BelongsToTenants;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'segment_id',
        'product_id',
        'height',
        'distributed_width',
        'quantity',  
        'spacing',
        'status',
        'alignment',
        'settings',
    ];

    protected $casts = [
        'height' => 'decimal:2',
        'distributed_width' => 'decimal:2',
        'settings' => 'array',
    ];

    protected $appends = [
        'settings',
    ];

    public function getSettingsAttribute($value)
    {
        return json_decode($value);
    }

    public function segment()
    {
        return $this->belongsTo(Segment::class);
    }

    public function product()
    {
        return $this->belongsTo(config('raptor.models.product', Product::class));
    }

    public function updateQuantity($amount, $isAbsolute = false)
    {
        if ($isAbsolute) {
            $this->quantity = $amount;
        } else {
            $this->quantity = max(0, $this->quantity + $amount);
        }

        $this->save();

        // Recalcula automaticamente as larguras distribuídas da shelf
        if ($this->segment && $this->segment->shelf) {
            $this->segment->shelf->calculateDistributedWidths();
        }

        return $this;
    }

    /**
     * Boot method para recalcular larguras quando quantity mudar
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($layer) {
            // Recalcula larguras distribuídas quando quantity for alterada
            if ($layer->isDirty('quantity')) {
                // Carrega os relacionamentos necessários se não estiverem carregados
                if (!$layer->relationLoaded('segment')) {
                    $layer->load('segment');
                }
                if (!$layer->segment->relationLoaded('shelf')) {
                    $layer->segment->load('shelf');
                }
                
                // Recalcula se tiver os relacionamentos necessários
                if ($layer->segment && $layer->segment->shelf) {
                    $layer->segment->shelf->calculateDistributedWidths();
                }
            }
        });
    }
}
