<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Requests\Planogram;

use Callcocam\Plannerate\Enums\PlanogramStatus;
use Callcocam\Plannerate\Http\Requests\BaseFormRequest;

/**
 * Class StoreRequest
 * 
 * Classe de validação para requisições de criação de registros Plannerate.
 * Define regras de validação e mensagens personalizadas.
 */
class StorePlanogramRequest extends BaseFormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     * 
     * @return bool Retorna true se autorizado, false caso contrário
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define as regras de validação aplicáveis à requisição.
     * 
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 
            'description' => ['nullable', 'string', 'max:255'],
            'store_id' => ['nullable', 'string', 'exists:stores,id'],
            'cluster_id' => ['nullable', 'string', 'exists:clusters,id'],
            'department_id' => ['nullable', 'string', 'exists:departaments,id'],
            'start_date' => ['nullable', 'date', 'before_or_equal:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', 'in:' . implode(',', array_column(PlanogramStatus::cases(), 'value'))],
        ];
    }

    /**
     * Define mensagens personalizadas para erros de validação.
     * 
     * @return array Mensagens personalizadas
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome do planograma é obrigatório.',
            'name.max' => 'O nome do planograma não pode ter mais de :max caracteres.',
            'slug.required' => 'O slug é obrigatório.',
            'slug.unique' => 'Este slug já está em uso. Por favor, escolha outro.',
            'slug.max' => 'O slug não pode ter mais de :max caracteres.',
            'store_id.exists' => 'A loja selecionada não existe.',
            'cluster_id.exists' => 'O cluster selecionado não existe.',
            'department_id.exists' => 'O departamento selecionado não existe.',
            'start_date.date' => 'A data de início deve ser uma data válida.',
            'start_date.before_or_equal' => 'A data de início deve ser anterior ou igual à data de término.',
            'end_date.date' => 'A data de término deve ser uma data válida.',
            'end_date.after_or_equal' => 'A data de término deve ser posterior ou igual à data de início.',
            'status.required' => 'O status é obrigatório.',
            'status.in' => 'O status selecionado é inválido.',
        ];
    }

    /**
     * Opcionalmente, você pode preparar os dados antes da validação
     * sobrecarregando o método prepareForValidation() aqui
     * 
     * protected function prepareForValidation(): void
     * {
     *     $this->merge([
     *         'field' => transform_field($this->field),
     *     ]);
     * }
     */
}
