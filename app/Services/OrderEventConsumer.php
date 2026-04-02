<?php

namespace App\Services;

use App\Models\OutboxEvent;

class OrderEventConsumer
{
    public function __construct(
        private readonly SagaOrchestrator $saga,
    ) {}

    /**
     * 아웃박스 이벤트를 타입에 따라 적절한 핸들러로 라우팅
     */
    public function handle(OutboxEvent $event): void
    {
        match ($event->event_type) {
            'ORDER_CREATED' => $this->saga->handleOrderCreated($event),
            default => throw new \RuntimeException("알 수 없는 이벤트 타입: {$event->event_type}"),
        };
    }
}
