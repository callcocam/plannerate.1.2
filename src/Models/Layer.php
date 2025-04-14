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
        'quantity',  
        'spacing',
        'status',
        'settings',
    ];

    protected $casts = [
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

        return $this;
    }
}
