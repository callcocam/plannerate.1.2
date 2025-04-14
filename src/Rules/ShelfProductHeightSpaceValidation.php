<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Rules;

use Callcocam\Plannerate\Models\Product;
use Callcocam\Plannerate\Models\Shelf;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ShelfProductHeightSpaceValidation implements ValidationRule
{
    protected $shelfId;

    protected $request;

    public function __construct($shelfId, $request)
    {
        $this->shelfId = $shelfId;
        $this->request = $request;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $shelf = Shelf::find($this->shelfId);
        if (! $shelf) {
            $fail('Prateleira inválida.');

            return;
        }
        $product = Product::find(data_get($value, 'product_id'));
        if (! $product) {
            $fail('Produto inválido.');

            return;
        }
        $section = $shelf->section;
        $nextShelf = $section->shelves()->where('position', '>', $shelf->position)->orderBy('position')->first();
        if (! $nextShelf) {
            $nextShelfPosition = $section->height - $product->height;
        } else {
            $nextShelfPosition = $nextShelf->position;
        }
        $currentShelfPosition = $shelf->position;
        $layerHeight = $product->height * data_get($value, 'quantity');
        $totalHeight = $currentShelfPosition + $layerHeight;
        if ($totalHeight >= $nextShelfPosition) {
            $fail('O produto não cabe na prateleira.');
        }
    }
}
