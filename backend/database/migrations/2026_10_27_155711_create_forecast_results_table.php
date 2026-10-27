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
        Schema::create('forecast_results', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('forecast_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('model', 32);
            $table->unsignedSmallInteger('history_weeks');
            $table->unsignedInteger('predicted_units');
            $table->unsignedInteger('available_quantity');
            $table->unsignedInteger('minimum_stock');
            $table->unsignedInteger('incoming_quantity')->default(0);
            $table->unsignedInteger('recommended_restock');
            $table->decimal('mae', 12, 4);
            $table->decimal('rmse', 12, 4);
            $table->decimal('wape', 8, 4);
            $table->timestamps();

            $table->unique(['forecast_run_id', 'item_id']);
            $table->index(['operational_unit_id', 'recommended_restock']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecast_results');
    }
};
