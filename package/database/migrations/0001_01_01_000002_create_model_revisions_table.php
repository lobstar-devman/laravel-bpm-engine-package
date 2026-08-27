<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('model_definition_id')->constrained('model_definitions')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->text('xml');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_revisions');
    }
};
