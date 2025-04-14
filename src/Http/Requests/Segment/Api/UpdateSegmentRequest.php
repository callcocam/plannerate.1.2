<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Requests\Segment\Api;

use Callcocam\Plannerate\Rules\ShelfHeightSpaceValidation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSegmentRequest extends FormRequest
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
                'integer',
                'min:1',
                new ShelfHeightSpaceValidation($this->route('segment')->id, $this->request->all()),
            ], 
        ];
    }
}
