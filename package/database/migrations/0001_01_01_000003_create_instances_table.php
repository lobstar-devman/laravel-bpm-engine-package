<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('model_revision_id')->constrained('model_revisions')->cascadeOnDelete();
            $table->string('type'); // process | case
            $table->string('current_state');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instances');
    }
};
