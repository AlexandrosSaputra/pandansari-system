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
            $table->string('safety_stock_mode', 16)->default('MANUAL')->after('minimum_stock');
            $table->decimal('target_service_level', 5, 2)->default(95)->after('safety_stock_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_items', function (Blueprint $table) {
            $table->dropColumn(['safety_stock_mode', 'target_service_level']);
        });
    }
};
