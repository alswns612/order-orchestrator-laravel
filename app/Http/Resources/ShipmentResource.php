<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
        ];
    }
}
