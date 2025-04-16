<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Models;
 
use Tall\Sluggable\HasSlug;
use App\Models\User;
use Callcocam\LaraGatekeeper\Core\Landlord\BelongsToTenants;
use Callcocam\Plannerate\Enums\SectionStatus; 
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tall\Sluggable\SlugOptions;

class Section extends Model
{
    use HasFactory, HasSlug, HasUlids, SoftDeletes, BelongsToTenants;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'gondola_id',
        'name',
        'code',
        'slug',
        'width',
        'height',
        'num_shelves',
        'base_height',
        'base_depth',
        'base_width',
        'cremalheira_width',
        'hole_height',
        'hole_width',
        'hole_spacing',
        'alignment',
        'ordering',
        'settings',
        'status',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'num_shelves' => 'integer',
        'base_height' => 'integer',
        'base_depth' => 'integer',
        'base_width' => 'integer',
        'cremalheira_width' => 'integer',
        'hole_height' => 'integer',
        'hole_width' => 'integer',
        'hole_spacing' => 'integer',
        'ordering' => 'integer',
        'settings' => 'json',
        'status' => SectionStatus::class,
    ];
 

    public function gondola(): BelongsTo
    {
        return $this->belongsTo(Gondola::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
 
    public function shelves(): HasMany
    {
        return $this->hasMany(Shelf::class)->orderBy('ordering');
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
