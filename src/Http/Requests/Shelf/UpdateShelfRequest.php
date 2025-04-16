<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Requests\Shelf;

use Callcocam\Plannerate\Enums\ShelfStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShelfRequest extends FormRequest
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
            'height' => ['sometimes', 'required', 'numeric', 'min:0'],
            'depth' => ['sometimes', 'required', 'numeric', 'min:0'],
            'ordering' => ['sometimes', 'required', 'integer', 'min:0'],
            'position' => ['sometimes', 'required', 'numeric', 'min:0'], 
            'segment' => ['sometimes', 'required', 'array'], 
            'status' => ['sometimes', 'required', Rule::enum(ShelfStatus::class)],
            'alignment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
