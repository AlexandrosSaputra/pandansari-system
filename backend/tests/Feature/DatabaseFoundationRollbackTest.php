<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseFoundationRollbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = env('DB_ROLLBACK_DATABASE', 'pandansari_rollback_testing');

        if (! str_ends_with($database, '_testing')) {
            $this->fail('The rollback test database name must end with _testing.');
        }

        $connection = config('database.connections.mysql');
        $connection['database'] = $database;

        config([
            'database.default' => 'foundation_rollback',
            'database.connections.foundation_rollback' => $connection,
        ]);

        DB::purge('foundation_rollback');
        Schema::dropAllTables();
    }

    public function test_foundation_can_migrate_rollback_and_migrate_again_preserving_existing_users(): void
    {
        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'database/migrations/0001_01_01_000001_create_cache_table.php',
                'database/migrations/0001_01_01_000002_create_jobs_table.php',
                'database/migrations/2026_09_28_154248_create_personal_access_tokens_table.php',
            ],
            '--no-interaction' => true,
        ])->assertSuccessful();
        $userId = DB::table('users')->insertGetId([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => 'not-used-by-this-test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $originalUser = (array) DB::table('users')->find($userId);

        $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            ...$originalUser,
            'role_id' => null,
            'operational_unit_id' => null,
            'is_active' => true,
        ]);
        $this->assertTrue(Schema::hasTable('activity_logs'));

        $this->artisan('migrate:rollback', ['--step' => 27, '--no-interaction' => true])->assertSuccessful();

        $this->assertSame($originalUser, (array) DB::table('users')->find($userId));
        $this->assertFalse(Schema::hasColumn('users', 'role_id'));
        $this->assertFalse(Schema::hasColumn('users', 'operational_unit_id'));
        $this->assertFalse(Schema::hasColumn('users', 'is_active'));
        $this->assertFalse(Schema::hasColumn('users', 'username'));
        $this->assertDatabaseCount('migrations', 4);
        foreach (['roles', 'permissions', 'role_permissions', 'user_role_assignments', 'operational_units', 'items', 'unit_items', 'inventory_locations', 'inventory_balances', 'stock_movements', 'stock_transfers', 'stock_transfer_items', 'transactions', 'transaction_items', 'activity_logs', 'historical_sales', 'forecast_runs', 'forecast_results', 'forecast_result_decisions'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Table remains after rollback: {$table}");
        }
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));

        $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();

        $this->assertDatabaseCount('migrations', 31);
        $this->assertDatabaseHas('users', [...$originalUser, 'is_active' => true]);
        $this->assertTrue(Schema::hasColumn('users', 'username'));
        $this->assertTrue(Schema::hasTable('activity_logs'));
    }
}
