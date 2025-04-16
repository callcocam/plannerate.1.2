<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Requests\Shelf;

use Callcocam\Plannerate\Enums\ShelfStatus;
use Callcocam\Plannerate\Http\Requests\BaseFormRequest;
use Callcocam\Plannerate\Models\Product;
use Callcocam\Plannerate\Rules\ShelfStoreSpacingValidation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreShelfRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'segment' => [
                'sometimes',
                'required',
                'array',
                new ShelfStoreSpacingValidation(),
            ], 
            'alignment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
