<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('model_revision_id')->constrained('model_revisions')->cascadeOnDelete();
            $table->foreignUuid('instance_id')->nullable()->constrained('instances')->nullOnDelete();
            $table->jsonb('inputs');
            $table->jsonb('outputs');
            $table->timestamp('evaluated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_logs');
    }
};
