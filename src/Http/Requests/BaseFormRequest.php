<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Requests;

use Callcocam\LaraGatekeeper\Core\Landlord\Facades\Landlord;
use Illuminate\Foundation\Http\FormRequest;

abstract class BaseFormRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $tenantId = Landlord::getTenantId('tenant_id');

        $this->merge([
            'user_id' => auth()->id(),
            'tenant_id' => $tenantId,
        ]);
    }

    protected function withTenantRule(string $table, string $column = 'id')
    {
        return "exists:{$table},{$column},tenant_id,".$this->tenant_id;
    }
}
