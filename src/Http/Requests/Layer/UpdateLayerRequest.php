<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Requests\Layer;

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
            'validated' => [
                'sometimes',
                'integer',
                'min:0',
                new ShelfWidthSpaceValidation($this->route('layer')->id, $this->request->all()),
            ],
            'quantity' => [
                'sometimes',
                'integer',
                'min:1',
                new ShelfWidthSpaceValidation($this->route('layer')->id, $this->request->all()),
            ],
            'alignment' => ['nullable', 'string', 'max:255'],
            'settings' => [
                'nullable',
                'array',
            ],
            'segment' => [
                'nullable',
                'array',
            ],
            'product' => [
                'nullable',
                'array',
            ],
        ];
    }

    // TODO: Add validation for max quantity per layer ex: section width / product width
    protected function maxQuantityPerLayer(string $attribute, int $value, Closure $fail): int
    {
        return 5;
    }
}
