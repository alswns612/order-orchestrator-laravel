<?php

namespace App\Services;

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\OutboxEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SagaOrchestrator
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * ORDER_CREATED 이벤트 처리 — 사가 실행
     * 1. 결제 승인
     * 2. 주문 상태 → PAID
     * 3. 재고 확인
     * 4. 배송 요청
     * 5. 주문 상태 → SHIPPED
     */
    public function handleOrderCreated(OutboxEvent $event): void
    {
        $orderId = $event->payload['order_id'];
        $order = Order::with(['payment', 'inventoryReservation', 'shipment'])->findOrFail($orderId);

        $completedSteps = [];

        try {
            $this->authorizePayment($order);
            $completedSteps[] = 'PAYMENT_AUTHORIZED';

            $order->update(['status' => OrderStatus::PAID]);
            $completedSteps[] = 'STATUS_PAID';

            $this->confirmInventory($order);
            $completedSteps[] = 'INVENTORY_CONFIRMED';

            $this->requestShipment($order);
            $completedSteps[] = 'SHIPMENT_REQUESTED';

            $order->update(['status' => OrderStatus::SHIPPED]);
            $completedSteps[] = 'STATUS_SHIPPED';

            $this->auditLog->log($orderId, 'SAGA_COMPLETED', '사가 정상 완료');

            Log::info("사가 완료: 주문 {$orderId}");
        } catch (\Throwable $e) {
            Log::error("사가 실패: 주문 {$orderId}", [
                'error' => $e->getMessage(),
                'completed_steps' => $completedSteps,
            ]);

            $this->compensate($order, $completedSteps, $e);

            throw $e;
        }
    }

    private function authorizePayment(Order $order): void
    {
        $order->payment->update([
            'status' => PaymentStatus::AUTHORIZED,
            'processed_at' => Carbon::now(),
        ]);
    }

    private function confirmInventory(Order $order): void
    {
        $order->inventoryReservation->update([
            'status' => InventoryReservationStatus::CONFIRMED,
        ]);
    }

    private function requestShipment(Order $order): void
    {
        $order->shipment->update([
            'status' => ShipmentStatus::SHIPPED,
            'carrier' => 'CJ대한통운',
            'tracking_number' => 'TRK-' . strtoupper(substr(md5($order->id), 0, 12)),
            'shipped_at' => Carbon::now(),
        ]);
    }

    /**
     * 보상 트랜잭션 — 완료된 단계를 역순으로 롤백
     */
    private function compensate(Order $order, array $completedSteps, \Throwable $originalError): void
    {
        DB::transaction(function () use ($order, $completedSteps, $originalError) {
            $reversed = array_reverse($completedSteps);

            foreach ($reversed as $step) {
                try {
                    match ($step) {
                        'SHIPMENT_REQUESTED' => $order->shipment->update([
                            'status' => ShipmentStatus::REQUESTED,
                            'carrier' => null,
                            'tracking_number' => null,
                            'shipped_at' => null,
                        ]),
                        'INVENTORY_CONFIRMED' => $order->inventoryReservation->update([
                            'status' => InventoryReservationStatus::RELEASED,
                        ]),
                        'PAYMENT_AUTHORIZED' => $order->payment->update([
                            'status' => PaymentStatus::CANCELLED,
                        ]),
                        default => null,
                    };

                    $this->auditLog->log(
                        $order->id,
                        "COMPENSATE_{$step}",
                        "보상 트랜잭션: {$step} 롤백",
                    );
                } catch (\Throwable $e) {
                    Log::error("보상 트랜잭션 실패: {$step}", ['error' => $e->getMessage()]);
                }
            }

            $order->update(['status' => OrderStatus::FAILED]);

            $this->auditLog->log(
                $order->id,
                'SAGA_COMPENSATED',
                "사가 보상 완료: {$originalError->getMessage()}",
                ['completed_steps' => $completedSteps],
            );
        });
    }
}
