<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GondolaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'planogram_id' => $this->planogram_id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'width' => $this->width,
            'height' => $this->height,
            'base_height' => $this->base_height,
            'thickness' => $this->thickness, // espessura da gramalheira
            'scale_factor' => $this->scale_factor,
            'location' => $this->location,
            'alignment' => $this->alignment,
            'flow' => $this->flow,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            // 'planogram' => new PlanogramResource($this->whenLoaded('planogram')),
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
            'linked_map_gondola_id' => $this->linked_map_gondola_id,
            'linked_map_gondola_category' => $this->linked_map_gondola_category,
        ];
    }
}
