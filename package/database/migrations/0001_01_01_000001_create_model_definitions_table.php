<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('standard'); // bpmn | cmmn | dmn
            $table->string('key');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_definitions');
    }
};
