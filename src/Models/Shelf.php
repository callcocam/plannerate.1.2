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

    /**
     * Calcula e atualiza as larguras distribuídas dos segments desta shelf
     * baseado no alinhamento da gôndola
     */
    public function calculateDistributedWidths(): void
    {
        // Carrega a seção e gôndola para obter o alinhamento
        $this->load(['section.gondola', 'segments.layer.product']);
        
        $gondola = $this->section->gondola ?? null;
        $alignment = $gondola->alignment ?? 'left';
        
        // Se não for justify, usa largura natural dos produtos
        if ($alignment !== 'justify') {
            foreach ($this->segments as $segment) {
                $segment->distributed_width = null;
                $segment->save();
                
                if ($segment->layer) {
                    $segment->layer->distributed_width = null;
                    $segment->layer->save();
                }
            }
            return;
        }

        // Para justify, calcula a distribuição
        $sectionWidth = $this->section->width ?? 130; // largura padrão
        $totalSegments = $this->segments->count();
        
        if ($totalSegments === 0) {
            return;
        }

        // Calcula largura total natural de todos os produtos
        $totalNaturalWidth = 0;
        $totalProducts = 0;
        
        foreach ($this->segments as $segment) {
            if ($segment->layer && $segment->layer->product) {
                $productWidth = $segment->layer->product->width ?? 5; // largura padrão produto
                $quantity = $segment->layer->quantity ?? 1;
                $naturalWidth = $productWidth * $quantity;
                
                $totalNaturalWidth += $naturalWidth;
                $totalProducts += $quantity;
            }
        }

        // Se largura natural excede a seção, usa proporção
        if ($totalNaturalWidth > $sectionWidth) {
            $scaleFactor = $sectionWidth / $totalNaturalWidth;
            
            foreach ($this->segments as $segment) {
                if ($segment->layer && $segment->layer->product) {
                    $productWidth = $segment->layer->product->width ?? 5;
                    $quantity = $segment->layer->quantity ?? 1;
                    $naturalWidth = $productWidth * $quantity;
                    
                    $distributedWidth = $naturalWidth * $scaleFactor;
                    
                    $segment->distributed_width = round($distributedWidth, 2);
                    $segment->save();
                    
                    $segment->layer->distributed_width = round($distributedWidth, 2);
                    $segment->layer->save();
                }
            }
        } else {
            // Se cabe, distribui o espaço extra proporcionalmente
            $extraSpace = $sectionWidth - $totalNaturalWidth;
            $spacePerProduct = $totalProducts > 0 ? $extraSpace / $totalProducts : 0;
            
            foreach ($this->segments as $segment) {
                if ($segment->layer && $segment->layer->product) {
                    $productWidth = $segment->layer->product->width ?? 5;
                    $quantity = $segment->layer->quantity ?? 1;
                    $naturalWidth = $productWidth * $quantity;
                    
                    $extraForThisSegment = $spacePerProduct * $quantity;
                    $distributedWidth = $naturalWidth + $extraForThisSegment;
                    
                    $segment->distributed_width = round($distributedWidth, 2);
                    $segment->save();
                    
                    $segment->layer->distributed_width = round($distributedWidth, 2);
                    $segment->layer->save();
                }
            }
        }
    }
}
