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
        Schema::table('unit_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('supplier_lead_time_days')->default(0)->after('minimum_stock');
            $table->unsignedInteger('order_pack_size')->default(1)->after('supplier_lead_time_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_items', function (Blueprint $table) {
            $table->dropColumn(['supplier_lead_time_days', 'order_pack_size']);
        });
    }
};
