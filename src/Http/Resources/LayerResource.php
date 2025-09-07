<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'segment_id' => $this->segment_id,
            'product_id' => $this->product_id,
            'height' => $this->height,
            'quantity' => $this->quantity,
            'spacing' => $this->spacing,
            'settings' => $this->settings,
            'alignment' => $this->alignment, 
            'segment' => new SegmentResource($this->whenLoaded('segment')),
            'reload' => now()->diffInSeconds($this->updated_at) < 5,
            'ppp' => 2,
        ];

        if (class_exists('App\Http\Resources\ProductSingleResource')) {
            $data['product'] = app('App\Http\Resources\ProductSingleResource', [
                'resource' => $this->whenLoaded('product'),
            ]);
        }
        return $data;
    }
}
