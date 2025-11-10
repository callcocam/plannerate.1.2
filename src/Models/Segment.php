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
use Spatie\Activitylog\LogOptions;

class Segment extends Model
{
    use HasUlids, SoftDeletes, BelongsToTenants;
    use \Spatie\Activitylog\Traits\LogsActivity;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'shelf_id',
        'width',
        'ordering',
        'position',
        'quantity',
        'spacing',
        'settings',
        'alignment',
        'status',
        'deleted_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'deleted_at' => 'datetime',
    ];

    public function layer()
    {
        return $this->hasOne(Layer::class);
    }

    public function shelf()
    {
        return $this->belongsTo(Shelf::class);
    }

    
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['*']);
        // Chain fluent methods for configuration options
    }
}
