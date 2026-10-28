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
        Schema::table('forecast_results', function (Blueprint $table) {
            $table->unsignedSmallInteger('supplier_lead_time_days')->default(0)->after('minimum_stock');
            $table->unsignedInteger('order_pack_size')->default(1)->after('supplier_lead_time_days');
            $table->unsignedInteger('unrounded_restock')->nullable()->after('incoming_quantity');
            $table->date('order_by_date')->nullable()->after('recommended_restock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forecast_results', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_lead_time_days',
                'order_pack_size',
                'unrounded_restock',
                'order_by_date',
            ]);
        });
    }
};
