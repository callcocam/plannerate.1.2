<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Requests\Layer\Api;

use Callcocam\Plannerate\Rules\ShelfSpacingValidation;
use Callcocam\Plannerate\Rules\ShelfWidthSpaceValidation;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                new ShelfWidthSpaceValidation($this->route('layer')->id, $this->request->all()),
            ],
            'spacing' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                new ShelfSpacingValidation($this->route('layer')->id, $this->request->all()),
            ],
            'alignment' => ['nullable', 'string', 'max:255'],
        ];
    }

    // TODO: Add validation for max quantity per layer ex: section width / product width
    protected function maxQuantityPerLayer(string $attribute, int $value, Closure $fail): int
    {
        return 5;
    }
}
