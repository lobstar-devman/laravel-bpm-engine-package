<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expense_disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('instance_id')->constrained('instances')->cascadeOnDelete();
            $table->foreignUuid('expense_report_id')->constrained('expense_reports')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users');
            $table->foreignId('investigator_id')->nullable()->constrained('users');
            $table->foreignId('finance_director_id')->nullable()->constrained('users');
            $table->text('evidence_summary')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_disputes');
    }
};
