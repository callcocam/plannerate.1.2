<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Models;

use Callcocam\LaraGatekeeper\Core\Landlord\BelongsToTenants;
use Callcocam\LaraGatekeeper\Models\Tenant;
use Callcocam\Plannerate\Enums\PlanogramStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Tall\Sluggable\HasSlug;
use Tall\Sluggable\SlugOptions;

class Planogram extends Model
{
    use HasFactory, HasUlids, SoftDeletes, HasSlug, BelongsToTenants;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'datetime:Y-m-d',
        'end_date' => 'datetime:Y-m-d',
        'status' => PlanogramStatus::class,
    ];

   

    public function gondolas()
    { 
        return $this->hasMany(Gondola::class);
    }

    public function tenant()
    {
        return $this->belongsTo(config('plannerate.tenant_model', Tenant::class));
    }

    /**
     * @return SlugOptions
     */
    public function getSlugOptions()
    {
        if (is_string($this->slugTo())) {
            return SlugOptions::create()
                ->generateSlugsFrom($this->slugFrom())
                ->saveSlugsTo($this->slugTo());
        }
    }
}
