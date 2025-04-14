<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Requests\Gondola;

use Callcocam\Plannerate\Enums\GondolaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGondolaRequest extends FormRequest
{
    public function rules(): array
    {
        $gondolaId = $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('gondolas', 'slug')->ignore($gondolaId)
            ],
            'num_modulos' => ['nullable', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'side' => ['nullable', 'string', 'max:255'],
            'flow' => ['nullable', 'string', Rule::in(['left_to_right', 'right_to_left'])],
            'scale_factor' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(array_column(GondolaStatus::cases(), 'value'))],

            // Dados da seção (aninhados)
            'section' => ['nullable', 'array'],
            'section.name' => ['sometimes', 'required_with:section', 'string', 'max:255'],
            'section.width' => ['sometimes', 'required_with:section', 'numeric', 'min:1'],
            'section.height' => ['sometimes', 'required_with:section', 'numeric', 'min:1'],
            'section.base_height' => ['nullable', 'numeric', 'min:0'],
            'section.base_depth' => ['nullable', 'numeric', 'min:0'],
            'section.base_width' => ['nullable', 'numeric', 'min:0'],
            'section.cremalheira_width' => ['nullable', 'numeric', 'min:0'],
            'section.hole_height' => ['nullable', 'numeric', 'min:0'],
            'section.hole_width' => ['nullable', 'numeric', 'min:0'],
            'section.hole_spacing' => ['nullable', 'numeric', 'min:0'],
            'section.shelf_width' => ['nullable', 'numeric', 'min:0'],
            'section.shelf_height' => ['nullable', 'numeric', 'min:0'],
            'section.shelf_depth' => ['nullable', 'numeric', 'min:0'],
            'section.num_shelves' => ['nullable', 'integer', 'min:0'],
            'section.num_modulos' => ['nullable', 'integer', 'min:1'],
            'section.product_type' => ['nullable', 'string'],
            'section.settings' => ['nullable', 'array'],
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
            'name' => 'nome da gôndola',
            'slug' => 'slug',
            'num_modulos' => 'número de módulos',
            'location' => 'localização',
            'side' => 'lado',
            'flow' => 'fluxo',
            'scale_factor' => 'fator de escala',
            'status' => 'status',
            'section.name' => 'nome da seção',
            'section.width' => 'largura da seção',
            'section.height' => 'altura da seção',
            'section.base_height' => 'altura da base',
            'section.base_depth' => 'profundidade da base',
            'section.base_width' => 'largura da base',
            'section.cremalheira_width' => 'largura da cremalheira',
            'section.hole_height' => 'altura do furo',
            'section.hole_width' => 'largura do furo',
            'section.hole_spacing' => 'espaçamento entre furos',
            'section.shelf_width' => 'largura da prateleira',
            'section.shelf_height' => 'altura da prateleira',
            'section.shelf_depth' => 'profundidade da prateleira',
            'section.num_shelves' => 'número de prateleiras',
            'section.num_modulos' => 'número de módulos da seção',
            'section.product_type' => 'tipo de produto',
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
            'name.required' => 'O nome da gôndola é obrigatório.',
            'name.max' => 'O nome da gôndola não pode ter mais de :max caracteres.',
            'slug.unique' => 'Este slug já está em uso. Por favor, escolha outro.',
            'num_modulos.min' => 'É necessário pelo menos um módulo.',
            'flow.in' => 'O fluxo selecionado é inválido.',
            'scale_factor.min' => 'O fator de escala deve ser no mínimo 1.',
            'status.in' => 'O status selecionado é inválido.',
            'section.width.required_with' => 'A largura da seção é obrigatória.',
            'section.height.required_with' => 'A altura da seção é obrigatória.',
            'section.name.required_with' => 'O nome da seção é obrigatório.',
        ];
    }
}
