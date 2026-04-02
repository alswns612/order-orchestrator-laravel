<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForceOrderStatusRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\OrderResource;
use App\Models\AuditLog;
use App\Services\OrderService;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * 실패 주문 재처리
     */
    public function reprocess(string $id): OrderResource
    {
        $order = $this->orderService->reprocess($id);

        return new OrderResource($order);
    }

    /**
     * 주문 상태 강제 변경
     */
    public function forceStatus(ForceOrderStatusRequest $request, string $id): OrderResource
    {
        $order = $this->orderService->forceStatus(
            $id,
            \App\Enums\OrderStatus::from($request->validated('status')),
            $request->validated('reason'),
        );

        return new OrderResource($order);
    }

    /**
     * 감사 로그 조회
     */
    public function auditLogs(string $id): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $logs = AuditLog::where('order_id', $id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return AuditLogResource::collection($logs);
    }
}
