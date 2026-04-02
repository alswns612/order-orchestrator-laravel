<?php

namespace App\Services;

use App\Enums\OutboxEventStatus;
use App\Models\DeadLetterEvent;
use App\Models\OutboxEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OutboxProcessor
{
    public function __construct(
        private readonly OrderEventConsumer $eventConsumer,
    ) {}

    /**
     * 아웃박스 이벤트 디스패치
     * 대기 중인 이벤트를 가져와 처리하고, 실패 시 재시도 또는 DLQ로 이동
     */
    public function dispatch(int $limit = 10, bool $force = false): array
    {
        $query = OutboxEvent::where('status', OutboxEventStatus::PENDING)
            ->where(function ($q) use ($force) {
                if (!$force) {
                    $q->whereNull('next_retry_at')
                      ->orWhere('next_retry_at', '<=', Carbon::now());
                }
            })
            ->orderBy('created_at')
            ->limit($limit);

        $events = $query->get();

        $result = ['processed' => 0, 'published' => 0, 'failed' => 0];

        foreach ($events as $event) {
            $result['processed']++;

            try {
                $event->update(['status' => OutboxEventStatus::PROCESSING]);

                $this->eventConsumer->handle($event);

                $event->update([
                    'status' => OutboxEventStatus::PUBLISHED,
                    'published_at' => Carbon::now(),
                ]);

                $result['published']++;
            } catch (\Throwable $e) {
                Log::warning("아웃박스 이벤트 처리 실패: {$event->id}", [
                    'error' => $e->getMessage(),
                    'retry_count' => $event->retry_count,
                ]);

                $this->handleFailure($event, $e);
                $result['failed']++;
            }
        }

        return $result;
    }

    private function handleFailure(OutboxEvent $event, \Throwable $e): void
    {
        $retryCount = $event->retry_count + 1;

        if ($retryCount >= $event->max_retries) {
            $this->moveToDeadLetter($event, $e);
            return;
        }

        $backoffMs = min(1000 * (2 ** $retryCount), 60000);

        $event->update([
            'status' => OutboxEventStatus::PENDING,
            'retry_count' => $retryCount,
            'next_retry_at' => Carbon::now()->addMilliseconds($backoffMs),
            'last_error' => $e->getMessage(),
        ]);
    }

    private function moveToDeadLetter(OutboxEvent $event, \Throwable $e): void
    {
        DB::transaction(function () use ($event, $e) {
            DeadLetterEvent::create([
                'source_event_id' => $event->id,
                'aggregate_type' => $event->aggregate_type,
                'aggregate_id' => $event->aggregate_id,
                'event_type' => $event->event_type,
                'payload' => $event->payload,
                'last_error' => $e->getMessage(),
                'dead_lettered_at' => Carbon::now(),
            ]);

            $event->update([
                'status' => OutboxEventStatus::DEAD_LETTER,
                'last_error' => $e->getMessage(),
            ]);
        });
    }
}
