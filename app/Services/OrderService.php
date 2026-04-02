<?php

namespace App\Services;

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\OutboxEventStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\DeadLetterEvent;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * 주문 생성 — 단일 트랜잭션으로 주문 + 결제 + 재고 + 배송 + 아웃박스 이벤트 생성
     */
    public function create(array $data, ?string $idempotencyKey = null): Order
    {
        if ($idempotencyKey) {
            $existing = Order::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing->load(['payment', 'inventoryReservation', 'shipment']);
            }
        }

        return DB::transaction(function () use ($data, $idempotencyKey) {
            $totalAmount = collect($data['items'])->sum(fn ($item) => $item['quantity'] * $item['price']);

            $order = Order::create([
                'customer_id' => $data['customer_id'],
                'status' => OrderStatus::PENDING,
                'items' => $data['items'],
                'total_amount' => $totalAmount,
                'idempotency_key' => $idempotencyKey,
            ]);

            Payment::create([
                'order_id' => $order->id,
                'status' => PaymentStatus::PENDING,
                'amount' => $totalAmount,
            ]);

            $reservations = collect($data['items'])->map(fn ($item) => [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
            ])->all();

            InventoryReservation::create([
                'order_id' => $order->id,
                'status' => InventoryReservationStatus::RESERVED,
                'reservations' => $reservations,
            ]);

            Shipment::create([
                'order_id' => $order->id,
                'status' => ShipmentStatus::REQUESTED,
            ]);

            OutboxEvent::create([
                'aggregate_type' => 'Order',
                'aggregate_id' => $order->id,
                'event_type' => 'ORDER_CREATED',
                'payload' => [
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'items' => $order->items,
                    'total_amount' => $totalAmount,
                ],
                'status' => OutboxEventStatus::PENDING,
            ]);

            $this->auditLog->log($order->id, 'ORDER_CREATED', '주문 생성');

            return $order->load(['payment', 'inventoryReservation', 'shipment']);
        });
    }

    /**
     * 주문 상세 조회 — 관계 엔티티 eager load
     */
    public function find(string $id): Order
    {
        return Order::with(['payment', 'inventoryReservation', 'shipment'])
            ->findOrFail($id);
    }

    /**
     * 주문 상태 변경 — 상태 머신 검증 후 업데이트
     */
    public function updateStatus(string $id, OrderStatus $newStatus, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($id, $newStatus, $reason) {
            $order = Order::lockForUpdate()->findOrFail($id);

            $this->stateMachine->validateTransition($order->status, $newStatus);

            $oldStatus = $order->status;
            $order->update(['status' => $newStatus]);

            $this->auditLog->log(
                $order->id,
                'STATUS_CHANGED',
                $reason,
                ['from' => $oldStatus->value, 'to' => $newStatus->value],
            );

            return $order->load(['payment', 'inventoryReservation', 'shipment']);
        });
    }

    /**
     * 상태 강제 변경 — 상태 머신 검증을 우회 (관리자 전용)
     */
    public function forceStatus(string $id, OrderStatus $newStatus, string $reason): Order
    {
        return DB::transaction(function () use ($id, $newStatus, $reason) {
            $order = Order::lockForUpdate()->findOrFail($id);

            $oldStatus = $order->status;
            $order->update(['status' => $newStatus]);

            $this->auditLog->log(
                $order->id,
                'STATUS_FORCED',
                $reason,
                ['from' => $oldStatus->value, 'to' => $newStatus->value],
                'admin',
            );

            return $order->load(['payment', 'inventoryReservation', 'shipment']);
        });
    }

    /**
     * 실패 주문 재처리 — FAILED 상태를 PENDING으로 되돌리고 아웃박스 이벤트 재생성
     */
    public function reprocess(string $id): Order
    {
        return DB::transaction(function () use ($id) {
            $order = Order::lockForUpdate()->findOrFail($id);

            if ($order->status !== OrderStatus::FAILED) {
                throw new \InvalidArgumentException('FAILED 상태의 주문만 재처리할 수 있습니다.');
            }

            $order->update(['status' => OrderStatus::PENDING]);

            $order->payment()->update([
                'status' => PaymentStatus::PENDING,
                'processed_at' => null,
            ]);

            $order->inventoryReservation()->update([
                'status' => InventoryReservationStatus::RESERVED,
            ]);

            $order->shipment()->update([
                'status' => ShipmentStatus::REQUESTED,
                'carrier' => null,
                'tracking_number' => null,
                'shipped_at' => null,
            ]);

            OutboxEvent::create([
                'aggregate_type' => 'Order',
                'aggregate_id' => $order->id,
                'event_type' => 'ORDER_CREATED',
                'payload' => [
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'items' => $order->items,
                    'total_amount' => $order->total_amount,
                ],
                'status' => OutboxEventStatus::PENDING,
            ]);

            $this->auditLog->log($order->id, 'ORDER_REPROCESSED', '실패 주문 재처리', null, 'admin');

            return $order->load(['payment', 'inventoryReservation', 'shipment']);
        });
    }

    /**
     * DLQ 이벤트 단건 재처리 — 아웃박스 이벤트로 복원
     */
    public function reprocessDlqEvent(string $dlqId): OutboxEvent
    {
        return DB::transaction(function () use ($dlqId) {
            $dlq = DeadLetterEvent::findOrFail($dlqId);

            $outboxEvent = OutboxEvent::create([
                'aggregate_type' => $dlq->aggregate_type,
                'aggregate_id' => $dlq->aggregate_id,
                'event_type' => $dlq->event_type,
                'payload' => $dlq->payload,
                'status' => OutboxEventStatus::PENDING,
            ]);

            $dlq->delete();

            return $outboxEvent;
        });
    }

    /**
     * DLQ 이벤트 배치 재처리
     */
    public function batchReprocessDlq(?array $ids = null, ?string $eventType = null): int
    {
        $query = DeadLetterEvent::query();

        if ($ids) {
            $query->whereIn('id', $ids);
        }

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        $events = $query->get();
        $count = 0;

        foreach ($events as $dlq) {
            DB::transaction(function () use ($dlq) {
                OutboxEvent::create([
                    'aggregate_type' => $dlq->aggregate_type,
                    'aggregate_id' => $dlq->aggregate_id,
                    'event_type' => $dlq->event_type,
                    'payload' => $dlq->payload,
                    'status' => OutboxEventStatus::PENDING,
                ]);

                $dlq->delete();
            });

            $count++;
        }

        return $count;
    }
}
