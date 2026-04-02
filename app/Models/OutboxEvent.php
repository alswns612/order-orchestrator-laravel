<?php

namespace App\Models;

use App\Enums\OutboxEventStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload',
        'status',
        'retry_count',
        'max_retries',
        'next_retry_at',
        'last_error',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OutboxEventStatus::class,
            'payload' => 'array',
            'retry_count' => 'integer',
            'max_retries' => 'integer',
            'next_retry_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === OutboxEventStatus::PENDING;
    }

    public function hasExhaustedRetries(): bool
    {
        return $this->retry_count >= $this->max_retries;
    }
}
