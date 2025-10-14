<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SegmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'shelf_id' => $this->shelf_id, 
            'width' => $this->width,
            'ordering' => $this->ordering,
            'position' => $this->position,
            'quantity' => (int)$this->quantity ? $this->quantity : 0,
            'spacing' => $this->spacing,
            'settings' => $this->settings,
            'alignment' => $this->alignment,
            'layer' => new LayerResource($this->whenLoaded('layer')),
            // 'shelf' => new ShelfResource($this->whenLoaded('shelf')),
        ];
    }
}
