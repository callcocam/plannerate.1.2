<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GondolaZone extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'gondola_id',
        'name',
        'shelf_indexes',
        'performance_multiplier',
        'rules',
        'order',
    ];

    protected $casts = [
        'shelf_indexes' => 'array',
        'rules' => 'array',
        'performance_multiplier' => 'decimal:2',
        'order' => 'integer',
    ];

    /**
     * Relacionamento com Gondola
     */
    public function gondola(): BelongsTo
    {
        return $this->belongsTo(Gondola::class);
    }

    /**
     * Scope para ordenar por ordem de prioridade
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Verifica se uma prateleira pertence a esta zona
     */
    public function hasShelf(int $shelfIndex): bool
    {
        return in_array($shelfIndex, $this->shelf_indexes ?? []);
    }

    /**
     * Retorna a regra de prioridade
     */
    public function getPriorityRule(): ?string
    {
        return $this->rules['priority'] ?? null;
    }

    /**
     * Retorna o tipo de exposição
     */
    public function getExposureType(): ?string
    {
        return $this->rules['exposure_type'] ?? null;
    }

    /**
     * Retorna os filtros ABC
     */
    public function getAbcFilters(): array
    {
        return $this->rules['abc_filter'] ?? [];
    }

    /**
     * Retorna o percentual mínimo de margem
     */
    public function getMinMarginPercent(): ?float
    {
        return $this->rules['min_margin_percent'] ?? null;
    }

    /**
     * Retorna o percentual máximo de margem
     */
    public function getMaxMarginPercent(): ?float
    {
        return $this->rules['max_margin_percent'] ?? null;
    }

    /**
     * Retorna as marcas de referência
     */
    public function getReferenceBrands(): array
    {
        return $this->rules['reference_brands'] ?? [];
    }

    /**
     * Retorna o peso do fluxo de clientes
     */
    public function getCustomerFlowWeight(): float
    {
        return $this->rules['customer_flow_weight'] ?? 1.0;
    }
}

