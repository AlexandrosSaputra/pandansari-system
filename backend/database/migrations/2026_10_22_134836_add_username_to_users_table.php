<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique();
        });

        DB::table('users')->orderBy('id')->eachById(function (object $user): void {
            $base = Str::lower(Str::before((string) $user->email, '@'));
            $base = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', $base), '-');
            $base = $base !== '' ? $base : 'user';
            $candidate = Str::limit($base, 50, '');
            $suffix = 1;

            while (DB::table('users')->where('username', $candidate)->exists()) {
                $suffixText = '-'.$suffix;
                $candidate = Str::limit($base, 50 - strlen($suffixText), '').$suffixText;
                $suffix++;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
