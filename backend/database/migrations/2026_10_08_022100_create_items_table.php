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
        Schema::create('items', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->string('item_code')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('name');
            $table->string('type')->index();
            $table->string('uom')->nullable();
            $table->unsignedInteger('visitor_factor')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
