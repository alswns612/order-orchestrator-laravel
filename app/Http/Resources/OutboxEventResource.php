<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutboxEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'aggregate_type' => $this->aggregate_type,
            'aggregate_id' => $this->aggregate_id,
            'event_type' => $this->event_type,
            'payload' => $this->payload,
            'status' => $this->status->value,
            'retry_count' => $this->retry_count,
            'max_retries' => $this->max_retries,
            'next_retry_at' => $this->next_retry_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
