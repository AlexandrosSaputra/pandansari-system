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
        Schema::create('forecast_result_decisions', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('forecast_result_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->unsignedInteger('recommended_restock_snapshot');
            $table->unsignedInteger('final_restock_quantity')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['forecast_result_id', 'decided_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecast_result_decisions');
    }
};
