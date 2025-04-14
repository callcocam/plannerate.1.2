<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Rules;
 
use Callcocam\Plannerate\Models\Segment;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SegmentTransferValidation implements ValidationRule
{
    protected $segmentId;
    protected $request;
    public function __construct($segmentId, $request)
    {
        $this->segmentId = $segmentId;
        $this->request = $request;
    }
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Obtém o segmento, a prateleira e a seção
        $segment = Segment::with([
            'layer',
            'layer.product',
            'layer.product.image',
            'shelf.section', // Adicionado para garantir que shelf->section->width esteja disponível
        ])->find($this->segmentId);
        
        if (!$segment) {
            $fail('Segmento inválido.');
            return;
        }

        $shelf = $segment->shelf;
        if (!$shelf || !$shelf->section) {
            $fail('Prateleira ou seção não encontrada.');
            return;
        }

        $sectionWidth = $shelf->section->width;
        $totalWidth = 0;

        // Obtém o valor do atributo que está sendo validado (shelf_id)
        $targetShelfId = $value; 
        
        // Verifica se estamos transferindo para outra prateleira
        $isTransferringShelf = $targetShelfId != $segment->shelf_id;
        
        // Se estamos transferindo o segmento para outra prateleira, precisamos verificar
        // o espaço disponível na prateleira de destino
        if ($isTransferringShelf) {
            // Obtém a prateleira de destino e seus segmentos existentes
            $targetShelf = \Callcocam\Plannerate\Models\Shelf::with([
                'segments.layer.product',
                'section'
            ])->find($targetShelfId);
            
            if (!$targetShelf) {
                $fail('Prateleira de destino não encontrada.');
                return;
            }
            
            $sectionWidth = $targetShelf->section->width;
            
            // Calcula a largura total dos segmentos existentes na prateleira de destino
            foreach ($targetShelf->segments as $seg) {
                $currentLayer = $seg->layer;
                if (!$currentLayer || !$currentLayer->product) {
                    continue;
                }
                
                $productWidth = $currentLayer->product->width;
                $quantity = $currentLayer->quantity;
                $spacing = (float) $currentLayer->spacing;
                
                $segmentWidth = 0;
                if ($quantity > 0) {
                    $segmentWidth = ($productWidth * $quantity);
                    if ($quantity > 1) {
                        $segmentWidth += $spacing * ($quantity - 1);
                    }
                }
                
                $totalWidth += $segmentWidth;
            }
            
            // Adiciona a largura do segmento sendo transferido
            $transferredSegmentWidth = 0;
            if ($segment->layer && $segment->layer->product) {
                $productWidth = $segment->layer->product->width;
                $quantity = $segment->layer->quantity;
                $spacing = (float) $segment->layer->spacing;
                
                if ($quantity > 0) {
                    $transferredSegmentWidth = ($productWidth * $quantity);
                    if ($quantity > 1) {
                        $transferredSegmentWidth += $spacing * ($quantity - 1);
                    }
                }
            }
            
            $totalWidth += $transferredSegmentWidth;
        } else {
            // Se não estamos transferindo para outra prateleira, verificamos a largura 
            // na prateleira atual (comportamento original)
            foreach ($shelf->segments as $seg) {
                $currentLayer = $seg->layer;
                if (!$currentLayer || !$currentLayer->product) {
                    continue;
                }
                
                $productWidth = $currentLayer->product->width;
                $quantity = $currentLayer->quantity;
                $spacing = (float) $currentLayer->spacing;
                
                $segmentWidth = 0;
                if ($quantity > 0) {
                    $segmentWidth = ($productWidth * $quantity);
                    if ($quantity > 1) {
                        $segmentWidth += $spacing * ($quantity - 1);
                    }
                }
                
                $totalWidth += $segmentWidth;
            }
        }

        if ($totalWidth > $sectionWidth) {
            $fail(sprintf(
                'A largura total (%s) excede a largura da seção (%s).',
                $totalWidth,
                $sectionWidth
            ));
        }
    }
}