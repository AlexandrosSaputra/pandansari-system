<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('forecast_results', function (Blueprint $table) {
            $table->string('safety_stock_mode', 16)->default('MANUAL')->after('minimum_stock');
            $table->decimal('target_service_level', 5, 2)->default(95)->after('safety_stock_mode');
            $table->unsignedInteger('calculated_safety_stock')->default(0)->after('target_service_level');
            $table->unsignedInteger('safety_stock')->default(0)->after('calculated_safety_stock');
        });

        DB::table('forecast_results')->update([
            'calculated_safety_stock' => DB::raw('minimum_stock'),
            'safety_stock' => DB::raw('minimum_stock'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forecast_results', function (Blueprint $table) {
            $table->dropColumn([
                'safety_stock_mode',
                'target_service_level',
                'calculated_safety_stock',
                'safety_stock',
            ]);
        });
    }
};
