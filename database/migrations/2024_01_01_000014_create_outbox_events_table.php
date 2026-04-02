<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('aggregate_type', 64);
            $table->uuid('aggregate_id');
            $table->string('event_type', 64);
            $table->json('payload');
            $table->string('status', 20)->default('PENDING');
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('max_retries')->default(5);
            $table->timestamp('next_retry_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at'], 'idx_outbox_status_retry');
            $table->index(['aggregate_type', 'aggregate_id'], 'idx_outbox_aggregate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
