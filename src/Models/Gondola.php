<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Models;

use Callcocam\Plannerate\Enums\GondolaStatus;   
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model; 
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tall\Sluggable\HasSlug;
use Tall\Sluggable\SlugOptions;

class Gondola extends Model
{
    use HasFactory, HasSlug, HasUlids, SoftDeletes;

    protected $fillable = [ 
        'user_id',
        'planogram_id',
        'name',
        'slug',
        'location',
        'side',
        'flow',
        'num_modulos',
        'scale_factor',
        'alignment',
        'status',
    ];

    protected $casts = [
        'scale_factor' => 'integer',
        'status' => GondolaStatus::class,
    ];
 

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('ordering');
    }

    public function scopePublished($query)
    {
        return $query->where('status', GondolaStatus::Published);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', GondolaStatus::Draft);
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
