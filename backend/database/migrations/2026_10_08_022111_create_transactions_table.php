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
        Schema::create('transactions', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->string('transaction_number')->unique();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->unsignedInteger('visitor_count')->default(0);
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
