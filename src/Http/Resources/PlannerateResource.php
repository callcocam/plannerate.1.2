<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Resources;

 
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlannerateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'store_id' => $this->store_id,
            'store' => $this->whenLoaded('store', function () {
                return  $this->store->name;
            }),
            'cluster_id' => $this->cluster_id,
            'cluster' => $this->whenLoaded('cluster', function () {
                return $this->cluster->name;
            }),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', function () {
                return $this->department->name;
            }),
            'user' => $this->whenLoaded('user', function () {
                return $this->user->name;
            }),
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'end_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'status' => $this->status,
            'gondolas' => GondolaResource::collection($this->whenLoaded('gondolas')),
            'status_label' => $this->status,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'tenant' => $this->whenLoaded('tenant'),
        ];


        return  $data;
    }

    /**
     * Retorna o label do status para exibição
     * 
     * @param string|null $status
     * @return string
     */
    protected function getStatusLabel(?string $status): string
    {
        $statusMapping = [
            'draft' => 'Rascunho',
            'pending' => 'Pendente',
            'active' => 'Ativo',
            'completed' => 'Concluído',
            'inactive' => 'Inativo',
        ];

        return $statusMapping[$status] ?? 'Desconhecido';
    }
}
