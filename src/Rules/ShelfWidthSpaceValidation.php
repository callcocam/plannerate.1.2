<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Rules;

use Callcocam\Plannerate\Models\Layer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ShelfWidthSpaceValidation implements ValidationRule
{
    protected $layerId;
    protected $request;
    public function __construct($layerId, $request)
    {
        $this->layerId = $layerId;
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
        $layer = Layer::with(['product', 'segment.shelf.section'])->find($this->layerId);
        if (! $layer) {
            $fail('Layer inválido.');
            return;
        }

        $segment = $layer->segment;
        $shelf = $segment->shelf;
        $sectionWidth = $shelf->section->width;

        $totalWidth = 0;
        
        foreach ($shelf->segments as $seg) {
            $currentLayer = $seg->layer;
            $productWidth = $currentLayer->product->width;
            
            // Define a quantidade correta: usa o valor do request para o segmento atual,
            // e o valor do banco para os outros segmentos
            $quantity = ($currentLayer->id === $layer->id) ? $value : $currentLayer->quantity;
            
            // O espaçamento é entre produtos, então para n produtos, temos (n-1) espaçamentos
            $spacing = (float) $currentLayer->spacing;
            
            // Calcula a largura deste segmento:
            // Largura = largura dos produtos + espaçamento entre eles
            $segmentWidth = 0;
            if ($quantity > 0) {
                $segmentWidth = ($productWidth * $quantity);
                // Adiciona espaçamentos entre produtos (n-1 espaçamentos)
                if ($quantity > 1) {
                    $segmentWidth += $spacing * ($quantity - 1);
                }
            }
            
            $totalWidth += $segmentWidth;
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