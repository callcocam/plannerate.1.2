<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Requests\Section;

use Callcocam\Plannerate\Enums\SectionStatus;
use Callcocam\Plannerate\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule; 

class UpdateSectionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $sectionId = $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'nullable', 
                'string', 
                'max:50', 
                Rule::unique('sections', 'code')->ignore($sectionId)
            ],
            'slug' => [
                'nullable', 
                'string', 
                'max:255', 
                Rule::unique('sections', 'slug')->ignore($sectionId)
            ],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'num_shelves' => ['nullable', 'integer', 'min:0'],
            'base_height' => ['nullable', 'integer', 'min:0'],
            'base_depth' => ['nullable', 'integer', 'min:0'],
            'base_width' => ['nullable', 'integer', 'min:0'],
            'cremalheira_width' => ['nullable', 'integer', 'min:0'],
            'hole_height' => ['nullable', 'integer', 'min:0'],
            'hole_width' => ['nullable', 'integer', 'min:0'],
            'hole_spacing' => ['nullable', 'integer', 'min:0'],
            'ordering' => ['nullable', 'integer', 'min:0'],
            'settings' => ['nullable', 'array'],
            'alignment' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(array_column(SectionStatus::cases(), 'value'))],
            
            // Campos adicionais para atualização de prateleiras
            'shelf_height' => ['nullable', 'integer', 'min:0'],
            'shelf_depth' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome da seção',
            'code' => 'código',
            'slug' => 'slug',
            'width' => 'largura',
            'height' => 'altura',
            'num_shelves' => 'número de prateleiras',
            'base_height' => 'altura da base',
            'base_depth' => 'profundidade da base',
            'base_width' => 'largura da base',
            'cremalheira_width' => 'largura da cremalheira',
            'hole_height' => 'altura do furo',
            'hole_width' => 'largura do furo',
            'hole_spacing' => 'espaçamento entre furos',
            'ordering' => 'ordem',
            'settings' => 'configurações',
            'status' => 'status',
            'shelf_height' => 'altura da prateleira',
            'shelf_depth' => 'profundidade da prateleira',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da seção é obrigatório.',
            'name.max' => 'O nome da seção não pode ter mais de :max caracteres.',
            'code.unique' => 'Este código já está em uso. Por favor, escolha outro.',
            'code.max' => 'O código não pode ter mais de :max caracteres.',
            'slug.unique' => 'Este slug já está em uso. Por favor, escolha outro.',
            'width.min' => 'A largura deve ser no mínimo 1.',
            'height.min' => 'A altura deve ser no mínimo 1.',
            'num_shelves.min' => 'O número de prateleiras não pode ser negativo.',
            'status.in' => 'O status selecionado é inválido.',
        ];
    }
}