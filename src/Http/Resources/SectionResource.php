<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'gondola_id' => $this->gondola_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'width' => (int)$this->width,
            'height' => (int)$this->height,
            'num_shelves' => (int)$this->num_shelves,
            'base_height' => (int)$this->base_height,
            'base_depth' => (int)$this->base_depth,
            'base_width' => (int)$this->base_width,
            'hole_height' => (int)$this->hole_height,
            'hole_width' => (int)$this->hole_width,
            'hole_spacing' => (int)$this->hole_spacing, // espessura entre os furos
            'shelf_height' => (int)$this->shelf_height, // espessura da prateleira
            'cremalheira_width' => (int)$this->cremalheira_width,
            'ordering' => (int)$this->ordering,
            'alignment' => $this->alignment,
            'settings' => $this->settings,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->getLabel(),
                'color' => $this->status->color(),
            ],
            'shelves' => ShelfResource::collection($this->whenLoaded('shelves')),
            'gondola' => new GondolaResource($this->whenLoaded('gondola')),
        ];
    }
}
