<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOrderRequest;
use App\Http\Requests\Api\V1\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * 주문 생성
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        $order = $this->orderService->create(
            $request->validated(),
            $idempotencyKey,
        );

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 주문 상세 조회
     */
    public function show(string $id): OrderResource
    {
        $order = $this->orderService->find($id);

        return new OrderResource($order);
    }

    /**
     * 주문 상태 변경
     */
    public function updateStatus(UpdateOrderStatusRequest $request, string $id): OrderResource
    {
        $order = $this->orderService->updateStatus(
            $id,
            \App\Enums\OrderStatus::from($request->validated('status')),
            $request->validated('reason'),
        );

        return new OrderResource($order);
    }
}
