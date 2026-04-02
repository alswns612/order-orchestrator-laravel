<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'status' => $this->status->value,
            'items' => $this->items,
            'total_amount' => $this->total_amount,
            'idempotency_key' => $this->idempotency_key,
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'inventory_reservation' => new InventoryReservationResource($this->whenLoaded('inventoryReservation')),
            'shipment' => new ShipmentResource($this->whenLoaded('shipment')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
