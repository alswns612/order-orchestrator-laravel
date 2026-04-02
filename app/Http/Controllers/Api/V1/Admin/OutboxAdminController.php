<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OutboxEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BatchReprocessDlqRequest;
use App\Http\Requests\Api\V1\DispatchOutboxRequest;
use App\Http\Resources\DeadLetterEventResource;
use App\Http\Resources\OutboxEventResource;
use App\Models\DeadLetterEvent;
use App\Models\OutboxEvent;
use App\Services\OrderService;
use App\Services\OutboxProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutboxAdminController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly OutboxProcessor $outboxProcessor,
    ) {}

    /**
     * 대기 중인 아웃박스 이벤트 목록
     */
    public function pending(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $events = OutboxEvent::where('status', OutboxEventStatus::PENDING)
            ->orderBy('created_at')
            ->paginate(20);

        return OutboxEventResource::collection($events);
    }

    /**
     * 수동 아웃박스 디스패치
     */
    public function dispatch(DispatchOutboxRequest $request): JsonResponse
    {
        $limit = $request->validated('limit') ?? 10;
        $force = $request->validated('force') ?? false;

        $result = $this->outboxProcessor->dispatch($limit, $force);

        return response()->json([
            'message' => '디스패치 완료',
            'processed' => $result['processed'],
            'published' => $result['published'],
            'failed' => $result['failed'],
        ]);
    }

    /**
     * DLQ 이벤트 목록
     */
    public function dlqIndex(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $query = DeadLetterEvent::query()->orderByDesc('dead_lettered_at');

        if ($eventType = $request->query('event_type')) {
            $query->where('event_type', $eventType);
        }

        return DeadLetterEventResource::collection($query->paginate(20));
    }

    /**
     * DLQ 이벤트 단건 재처리
     */
    public function dlqReprocess(string $id): OutboxEventResource
    {
        $event = $this->orderService->reprocessDlqEvent($id);

        return new OutboxEventResource($event);
    }

    /**
     * DLQ 이벤트 배치 재처리
     */
    public function dlqBatchReprocess(BatchReprocessDlqRequest $request): JsonResponse
    {
        $count = $this->orderService->batchReprocessDlq(
            $request->validated('ids'),
            $request->validated('event_type'),
        );

        return response()->json([
            'message' => "DLQ 이벤트 {$count}건 재처리 완료",
            'reprocessed' => $count,
        ]);
    }
}
