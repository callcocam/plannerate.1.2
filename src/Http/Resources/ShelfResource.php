<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShelfResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'section_id' => $this->section_id,
            'code' => $this->code,
            'product_type' => $this->product_type,
            'shelf_width' => $this->shelf_width,
            'shelf_height' => $this->shelf_height,
            'shelf_depth' => $this->shelf_depth,
            'shelf_position' => $this->shelf_position,
            'quantity' => $this->quantity,
            'ordering' => $this->ordering,
            'spacing' => $this->spacing,
            'settings' => $this->settings,
            'status' => $this->status,
            'alignment' => $this->alignment,
            'segments' => SegmentResource::collection($this->whenLoaded('segments')),
            'section' => new SectionResource($this->whenLoaded('section')),
            'reload' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
