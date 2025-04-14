<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Requests\Segment;

use Callcocam\Plannerate\Http\Requests\BaseFormRequest;
use Callcocam\Plannerate\Rules\ShelfProductHeightSpaceValidation;

class StoreSegmentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'ulid', 'exists:tenants,id'],
            'width' => 'required|numeric|min:0',
            'ordering' => 'required|integer|min:0',
            'quantity' => 'required|integer|min:1',
            'spacing' => 'nullable|numeric|min:0',
            'settings' => 'nullable|array',
            'position' => 'nullable|integer|min:0',
            'status' => 'sometimes|string|in:draft,published',
            'product' => [
                'required',
                'array',
                new ShelfProductHeightSpaceValidation($this->route('shelf')->id, $this->request->all()),
            ],
        ];
    }
}
