<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $legacyEmail = 'admin@pandansari.local';
        $configuredEmail = config('auth.initial_superadmin_email', $legacyEmail);

        if (! is_string($configuredEmail) || $configuredEmail === $legacyEmail) {
            return;
        }

        $legacyUser = DB::table('users')->where('email', $legacyEmail)->first();

        if ($legacyUser === null) {
            return;
        }

        if (DB::table('users')->where('email', $configuredEmail)->exists()) {
            throw new LogicException('SUPERADMIN_EMAIL sudah digunakan oleh akun lain.');
        }

        DB::table('users')
            ->where('id', $legacyUser->id)
            ->update([
                'email' => $configuredEmail,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $legacyEmail = 'admin@pandansari.local';
        $configuredEmail = config('auth.initial_superadmin_email', $legacyEmail);

        if (! is_string($configuredEmail) || $configuredEmail === $legacyEmail) {
            return;
        }

        if (DB::table('users')->where('email', $legacyEmail)->exists()) {
            return;
        }

        DB::table('users')
            ->where('email', $configuredEmail)
            ->update([
                'email' => $legacyEmail,
                'updated_at' => now(),
            ]);
    }
};
