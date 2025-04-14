<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Rules;

use Callcocam\Plannerate\Models\Layer;
use Callcocam\Plannerate\Models\Shelf;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ShelfStoreSpacingValidation implements ValidationRule
{

    

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        // Verifica se o segmento está associado a uma prateleira e seção
        $segment = $value;
        if (! $segment) {
            $fail('Segmento inválido.');
            return;
        }

        $shelf = Shelf::with(['section'])->find(data_get($segment, 'shelf_id'));
        if (! $shelf || ! $shelf->section) {
            $fail('Prateleira ou seção não encontrada.');
            return;
        }
        $sectionWidth = $shelf->section->width;
        if ($sectionWidth <= 0) {
            $fail('A largura da seção deve ser maior que zero.');
            return;
        }
        $totalWidth = 0;
        $segSpacing = 0; // Inicializa o espaçamento do segmento atual
        foreach ($shelf->segments as $seg) {
            $productWidth = $seg->layer->product->width;
            $quantity = $seg->layer->quantity;
            // Define o espaçamento correto (usa o novo valor para o segmento atual)
            $segSpacing =   (float) $seg->layer->spacing;
            // Para n produtos, precisamos de (n-1) espaçamentos entre eles
            // Se quantity for 0 ou 1, não há espaçamento
            $totalSpacing = $quantity > 1 ? $segSpacing * $quantity : 0;

            $totalSpacing +=   $quantity;

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
