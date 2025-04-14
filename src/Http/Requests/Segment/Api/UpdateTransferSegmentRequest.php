<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Requests\Segment\Api;

use Callcocam\Plannerate\Rules\SegmentTransferValidation; 
use Illuminate\Foundation\Http\FormRequest;

class UpdateTransferSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shelf_id' => [ 
                new SegmentTransferValidation($this->route('segment')->id, $this->request->all()),
            ], 
        ];
    }
}
