<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dead_letter_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('source_event_id')->index();
            $table->string('aggregate_type', 64);
            $table->uuid('aggregate_id');
            $table->string('event_type', 64)->index();
            $table->json('payload');
            $table->text('last_error')->nullable();
            $table->timestamp('dead_lettered_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dead_letter_events');
    }
};
