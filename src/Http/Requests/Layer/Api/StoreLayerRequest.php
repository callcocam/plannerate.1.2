<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Requests\Layer\Api;

use Callcocam\Plannerate\Http\Requests\BaseFormRequest;
use Callcocam\Plannerate\Rules\ShelfProductHeightSpaceValidation;

class StoreLayerRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'ulid', 'exists:tenants,id'],
            'user_id' => ['required', 'ulid', 'exists:users,id'],
            'product_id' => [
                'required',
                'ulid',
                'exists:products,id',
                new ShelfProductHeightSpaceValidation($this->route('segment')->id, $this->request->all()),
            ],
            'height' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'spacing' => 'nullable|numeric|min:0',
            'settings' => 'nullable|array',
            'status' => 'sometimes|string|in:draft,published',
        ];
    }
}
