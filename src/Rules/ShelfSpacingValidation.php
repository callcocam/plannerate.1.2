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

class ShelfSpacingValidation implements ValidationRule
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
        $segSpacing = 0; // Inicializa o espaçamento do segmento atual
        foreach ($shelf->segments as $seg) {
            $productWidth = $seg->layer->product->width;
            $quantity = $seg->layer->quantity;
            // Define o espaçamento correto (usa o novo valor para o segmento atual)
            if ($seg->layer->id === $layer->id) {
                // Se for o segmento atual, usa o valor do request
                $segSpacing = (float) $value;
            } else {
                // Caso contrário, usa o valor do banco
                $segSpacing = (float) $seg->layer->spacing;
            }
            // Para n produtos, precisamos de (n-1) espaçamentos entre eles
            // Se quantity for 0 ou 1, não há espaçamento
            $totalSpacing = $quantity > 1 ? $segSpacing * $quantity : 0;

            // A largura total para este segmento é: largura dos produtos + espaçamento total
            $totalWidth += ($productWidth * $quantity);

            $totalWidth += $totalSpacing;
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
