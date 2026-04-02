<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeadLetterEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_event_id' => $this->source_event_id,
            'aggregate_type' => $this->aggregate_type,
            'aggregate_id' => $this->aggregate_id,
            'event_type' => $this->event_type,
            'payload' => $this->payload,
            'last_error' => $this->last_error,
            'dead_lettered_at' => $this->dead_lettered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
