<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Models;

use Callcocam\LaraGatekeeper\Core\Landlord\BelongsToTenants;
use Callcocam\Plannerate\Enums\ShelfStatus; 
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shelf extends Model
{
    use  HasUlids, SoftDeletes, BelongsToTenants;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'section_id',
        'code',
        'product_type',
        'shelf_width',
        'shelf_height',
        'shelf_depth',
        'shelf_position',
        'spacing',
        'ordering',
        'settings',
        'alignment',
        'status',
    ];

    protected $casts = [
        'shelf_width' => 'integer',
        'shelf_height' => 'integer',
        'shelf_depth' => 'integer',
        'shelf_position' => 'integer',
        'ordering' => 'integer',
        'settings' => 'json',
        'status' => ShelfStatus::class,
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function segments()
    {
        return $this->hasMany(Segment::class)->orderBy('ordering');
    }
}
