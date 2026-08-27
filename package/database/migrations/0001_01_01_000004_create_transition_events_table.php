<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transition_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('instance_id')->constrained('instances')->cascadeOnDelete();
            $table->string('event_type');
            $table->jsonb('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transition_events');
    }
};
