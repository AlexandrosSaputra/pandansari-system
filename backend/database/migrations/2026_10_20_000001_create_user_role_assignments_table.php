<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('operational_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'operational_unit_id'], 'user_role_assignment_unique');
            $table->index(['user_id', 'is_active']);
        });

        $now = now();
        DB::table('users')
            ->whereNotNull('role_id')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($now): void {
                DB::table('user_role_assignments')->insert(
                    $users->map(fn ($user): array => [
                        'user_id' => $user->id,
                        'role_id' => $user->role_id,
                        'operational_unit_id' => $user->operational_unit_id,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
    }
};
