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
use Spatie\Activitylog\LogOptions;
use Tall\Sluggable\SlugOptions;

class Section extends Model
{
    use HasFactory, HasSlug, HasUlids, SoftDeletes, BelongsToTenants;
    use \Spatie\Activitylog\Traits\LogsActivity;

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
        'cremalheira_width' => 'decimal:2',
        'hole_height' => 'decimal:2',
        'hole_width' => 'decimal:2',
        'hole_spacing' => 'decimal:2',
        'ordering' => 'integer',
        'settings' => 'array',
        'status' => SectionStatus::class,
    ];

    /**
     * Boot do modelo para adicionar logs nas operações de banco
     */
    protected static function boot()
    {
        parent::boot();

        // Log antes de salvar
        // static::saving(function ($section) {
        //     \Log::info('💾 [MODEL] Salvando seção no banco', [
        //         'section_id' => $section->id,
        //         'hole_width' => $section->hole_width,
        //         'hole_height' => $section->hole_height,
        //         'hole_spacing' => $section->hole_spacing,
        //         'settings' => $section->settings,
        //         'timestamp' => now()->toISOString()
        //     ]);
        // });

        // // Log após salvar
        // static::saved(function ($section) {
        //     \Log::info('✅ [MODEL] Seção salva no banco com sucesso', [
        //         'section_id' => $section->id,
        //         'hole_width' => $section->hole_width,
        //         'hole_height' => $section->hole_height,
        //         'hole_spacing' => $section->hole_spacing,
        //         'settings' => $section->settings,
        //         'timestamp' => now()->toISOString()
        //     ]);
        // });
    }


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
    
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['*']);
        // Chain fluent methods for configuration options
    }
}
