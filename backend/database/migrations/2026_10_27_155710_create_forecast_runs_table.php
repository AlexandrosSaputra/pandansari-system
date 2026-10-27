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
        Schema::create('forecast_runs', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->string('horizon', 24);
            $table->date('target_date');
            $table->date('forecast_start');
            $table->date('forecast_end');
            $table->date('history_through');
            $table->unsignedSmallInteger('forecast_weeks');
            $table->string('method', 64)->default('per_sku_baseline_selection');
            $table->json('metrics');
            $table->string('explanation_provider', 24)->default('none');
            $table->string('explanation_status', 24)->default('disabled');
            $table->json('explanation')->nullable();
            $table->timestamps();

            $table->index(['operational_unit_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecast_runs');
    }
};
