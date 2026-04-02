<?php

use App\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\OutboxAdminController;
use App\Http\Controllers\Api\V1\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 라우트
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // 공개 API — 주문
    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::patch('/{id}/status', [OrderController::class, 'updateStatus']);
    });

    // 관리자 API
    Route::prefix('admin')->group(function () {

        // 주문 관리
        Route::prefix('orders')->group(function () {
            Route::post('/{id}/reprocess', [AdminOrderController::class, 'reprocess']);
            Route::post('/{id}/force-status', [AdminOrderController::class, 'forceStatus']);
            Route::get('/{id}/audit-logs', [AdminOrderController::class, 'auditLogs']);
        });

        // 아웃박스 관리
        Route::prefix('outbox')->group(function () {
            Route::get('/pending', [OutboxAdminController::class, 'pending']);
            Route::post('/dispatch', [OutboxAdminController::class, 'dispatch']);
            Route::get('/dlq', [OutboxAdminController::class, 'dlqIndex']);
            Route::post('/dlq/{id}/reprocess', [OutboxAdminController::class, 'dlqReprocess']);
            Route::post('/dlq/reprocess', [OutboxAdminController::class, 'dlqBatchReprocess']);
        });
    });
});
