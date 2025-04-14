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

class ShelfSpaceValidation implements ValidationRule
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
        $segment = Segment::find($this->segmentId);
        if (! $segment) {
            $fail('Segmento inválido.');

            return;
        }
        $productHeight = data_get($this->request, 'layer.product.height');
        $quantity = data_get($this->request, 'quantity');

        $shelf = $segment->shelf;
        $section = $shelf->section;
        // ================== VALIDAÇÃO DE ALTURA ==================
        //  Vamos pegar a posição da próxima prateleira
        $nextShelf = $section->shelves()->where('position', '>', $shelf->position)->orderBy('position')->first();
        if (! $nextShelf) {
            $nextShelfPosition = $section->height - $productHeight;
        } else {
            $nextShelfPosition = $nextShelf->position;
        }

        // Vamos pegar a posição da prateleira atual
        $currentShelfPosition = $shelf->position;

        // Vamos pegar a altura do layer
        $layerHeight = $productHeight * $quantity;

        // Vamos somar da posição da prateleira atual até a posição da prateleira anterior
        $totalHeight = $currentShelfPosition + $layerHeight;

        // Vamos verificar se a altura total é maior que a altura da prateleira
        if ($totalHeight >= $nextShelfPosition) {
            $fail("A altura total das prateleiras ({$totalHeight}px) excede a altura disponível na seção ({$nextShelfPosition}px).");
        }

    }
}
