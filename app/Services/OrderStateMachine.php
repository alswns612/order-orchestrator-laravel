<?php

namespace App\Services;

use App\Enums\OrderStatus;

class OrderStateMachine
{
    /**
     * 허용된 상태 전이 맵
     * PENDING → PAID, FAILED
     * PAID → SHIPPED, FAILED
     */
    private const TRANSITIONS = [
        'PENDING' => ['PAID', 'FAILED'],
        'PAID' => ['SHIPPED', 'FAILED'],
        'SHIPPED' => [],
        'FAILED' => [],
    ];

    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        $allowed = self::TRANSITIONS[$from->value] ?? [];

        return in_array($to->value, $allowed, true);
    }

    public function validateTransition(OrderStatus $from, OrderStatus $to): void
    {
        if (!$this->canTransition($from, $to)) {
            throw new \InvalidArgumentException(
                "'{$from->value}'에서 '{$to->value}'(으)로의 상태 전이는 허용되지 않습니다."
            );
        }
    }

    public function getAllowedTransitions(OrderStatus $from): array
    {
        return self::TRANSITIONS[$from->value] ?? [];
    }
}
