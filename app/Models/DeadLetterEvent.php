<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DeadLetterEvent extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'source_event_id',
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload',
        'last_error',
        'dead_lettered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'dead_lettered_at' => 'datetime',
        ];
    }
}
