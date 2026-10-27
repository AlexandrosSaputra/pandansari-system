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
        Schema::create('historical_sales', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('week_id', 16);
            $table->date('week_start');
            $table->date('week_end');
            $table->string('source_outlet');
            $table->string('source_sku');
            $table->unsignedInteger('units_sold');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('sales_value', 17, 2);
            $table->string('source_file');
            $table->unsignedInteger('source_row');
            $table->timestamps();

            $table->unique(['operational_unit_id', 'item_id', 'week_start']);
            $table->index(['operational_unit_id', 'week_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historical_sales');
    }
};
