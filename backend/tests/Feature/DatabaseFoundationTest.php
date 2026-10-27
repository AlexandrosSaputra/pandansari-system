<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_domain_tables_and_critical_columns_are_available(): void
    {
        $tables = [
            'roles' => ['slug', 'is_active'],
            'permissions' => ['slug'],
            'role_permissions' => ['role_id', 'permission_id'],
            'user_role_assignments' => ['user_id', 'role_id', 'operational_unit_id', 'is_active'],
            'operational_units' => ['type', 'scanner_enabled', 'created_by'],
            'users' => ['role_id', 'operational_unit_id', 'is_active'],
            'items' => ['item_code', 'barcode', 'type', 'visitor_factor'],
            'unit_items' => ['operational_unit_id', 'item_id', 'selling_price', 'minimum_stock'],
            'inventory_locations' => ['type', 'operational_unit_id'],
            'inventory_balances' => ['quantity_on_hand', 'quantity_reserved'],
            'stock_movements' => ['direction', 'movement_type', 'quantity', 'reference_type', 'reference_id', 'performed_by', 'created_at'],
            'stock_transfers' => ['status', 'rejection_reason', 'responded_by', 'responded_at'],
            'stock_transfer_items' => ['stock_transfer_id', 'item_id', 'quantity'],
            'transactions' => ['status', 'visitor_count', 'subtotal', 'total', 'paid_by', 'paid_at'],
            'transaction_items' => ['unit_price', 'subtotal', 'input_method'],
            'activity_logs' => ['actor_id', 'action', 'subject_type', 'subject_id', 'metadata', 'created_at'],
            'historical_sales' => ['operational_unit_id', 'item_id', 'week_start', 'units_sold', 'source_file'],
            'forecast_runs' => ['requested_by', 'operational_unit_id', 'horizon', 'forecast_start', 'forecast_end', 'metrics', 'explanation'],
            'forecast_results' => ['forecast_run_id', 'item_id', 'predicted_units', 'available_quantity', 'recommended_restock', 'wape'],
        ];

        foreach ($tables as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "Missing column: {$table}.{$column}");
            }
            $this->assertFalse(Schema::hasColumn($table, 'deleted_at'), "Unexpected soft deletes: {$table}");
        }

        $this->assertFalse(Schema::hasColumn('stock_movements', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('activity_logs', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('items', 'selling_price'));
        $this->assertFalse(Schema::hasColumn('inventory_balances', 'available_stock'));
    }

    public function test_domain_identifiers_and_pairs_have_unique_constraints(): void
    {
        $constraints = [
            ['roles', ['slug']],
            ['permissions', ['slug']],
            ['role_permissions', ['role_id', 'permission_id']],
            ['user_role_assignments', ['user_id', 'role_id', 'operational_unit_id']],
            ['operational_units', ['code']],
            ['items', ['item_code']],
            ['items', ['barcode']],
            ['unit_items', ['operational_unit_id', 'item_id']],
            ['inventory_locations', ['code']],
            ['inventory_locations', ['operational_unit_id']],
            ['inventory_balances', ['inventory_location_id', 'item_id']],
            ['stock_transfers', ['transfer_number']],
            ['stock_transfer_items', ['stock_transfer_id', 'item_id']],
            ['transactions', ['transaction_number']],
            ['historical_sales', ['operational_unit_id', 'item_id', 'week_start']],
            ['forecast_results', ['forecast_run_id', 'item_id']],
        ];

        foreach ($constraints as [$table, $columns]) {
            $this->assertTrue(
                Schema::hasIndex($table, $columns, 'unique'),
                "Missing unique constraint: {$table} (".implode(', ', $columns).')',
            );
        }
    }

    public function test_foreign_keys_preserve_master_and_historical_records(): void
    {
        $relations = [
            'role_permissions' => ['role_id' => ['roles', 'cascade'], 'permission_id' => ['permissions', 'cascade']],
            'user_role_assignments' => ['user_id' => ['users', 'cascade'], 'role_id' => ['roles', 'restrict'], 'operational_unit_id' => ['operational_units', 'restrict']],
            'operational_units' => ['created_by' => ['users', 'set null']],
            'users' => ['role_id' => ['roles', 'restrict'], 'operational_unit_id' => ['operational_units', 'restrict']],
            'items' => ['created_by' => ['users', 'set null']],
            'unit_items' => ['operational_unit_id' => ['operational_units', 'restrict'], 'item_id' => ['items', 'restrict'], 'created_by' => ['users', 'set null']],
            'inventory_locations' => ['operational_unit_id' => ['operational_units', 'restrict'], 'created_by' => ['users', 'set null']],
            'inventory_balances' => ['inventory_location_id' => ['inventory_locations', 'restrict'], 'item_id' => ['items', 'restrict']],
            'stock_movements' => ['inventory_location_id' => ['inventory_locations', 'restrict'], 'item_id' => ['items', 'restrict'], 'performed_by' => ['users', 'restrict']],
            'stock_transfers' => ['source_location_id' => ['inventory_locations', 'restrict'], 'destination_location_id' => ['inventory_locations', 'restrict'], 'created_by' => ['users', 'restrict'], 'responded_by' => ['users', 'restrict']],
            'stock_transfer_items' => ['stock_transfer_id' => ['stock_transfers', 'cascade'], 'item_id' => ['items', 'restrict']],
            'transactions' => ['operational_unit_id' => ['operational_units', 'restrict'], 'created_by' => ['users', 'restrict'], 'paid_by' => ['users', 'restrict']],
            'transaction_items' => ['transaction_id' => ['transactions', 'cascade'], 'item_id' => ['items', 'restrict']],
            'activity_logs' => ['actor_id' => ['users', 'set null']],
            'historical_sales' => ['operational_unit_id' => ['operational_units', 'restrict'], 'item_id' => ['items', 'restrict']],
            'forecast_runs' => ['requested_by' => ['users', 'restrict'], 'operational_unit_id' => ['operational_units', 'restrict']],
            'forecast_results' => ['forecast_run_id' => ['forecast_runs', 'cascade'], 'operational_unit_id' => ['operational_units', 'restrict'], 'item_id' => ['items', 'restrict']],
        ];

        foreach ($relations as $table => $columns) {
            $foreignKeys = collect(Schema::getForeignKeys($table))->keyBy(fn (array $key): string => implode(',', $key['columns']));
            foreach ($columns as $column => [$target, $deleteAction]) {
                $this->assertTrue($foreignKeys->has($column), "Missing foreign key: {$table}.{$column}");
                $foreignKey = $foreignKeys->get($column);
                $this->assertSame($target, $foreignKey['foreign_table']);
                $this->assertSame(['id'], $foreignKey['foreign_columns']);
                $this->assertSame($deleteAction, strtolower($foreignKey['on_delete']));
            }
        }
    }

    public function test_lookup_and_filter_columns_are_indexed(): void
    {
        $indexes = [
            'users' => [['is_active']],
            'user_role_assignments' => [['user_id', 'is_active'], ['is_active']],
            'operational_units' => [['type'], ['is_active']],
            'items' => [['type'], ['is_active']],
            'unit_items' => [['is_active']],
            'stock_movements' => [['movement_type'], ['created_at'], ['reference_type', 'reference_id']],
            'stock_transfers' => [['status'], ['created_at']],
            'transactions' => [['status'], ['created_at'], ['paid_at']],
            'activity_logs' => [['action'], ['subject_type', 'subject_id'], ['created_at']],
            'historical_sales' => [['operational_unit_id', 'week_start']],
            'forecast_runs' => [['operational_unit_id', 'created_at']],
            'forecast_results' => [['operational_unit_id', 'recommended_restock']],
        ];

        foreach ($indexes as $table => $columnSets) {
            foreach ($columnSets as $columns) {
                $this->assertTrue(Schema::hasIndex($table, $columns), "Missing index: {$table} (".implode(', ', $columns).')');
            }
        }
    }

    public function test_multiple_services_and_warehouse_locations_can_have_null_optional_identifiers(): void
    {
        DB::table('items')->insert([
            ['item_code' => 'SERVICE-1', 'name' => 'Service One', 'type' => 'SERVICE', 'barcode' => null],
            ['item_code' => 'SERVICE-2', 'name' => 'Service Two', 'type' => 'SERVICE', 'barcode' => null],
        ]);
        DB::table('inventory_locations')->insert([
            ['code' => 'WH-1', 'name' => 'Warehouse One', 'type' => 'WAREHOUSE', 'operational_unit_id' => null],
            ['code' => 'WH-2', 'name' => 'Warehouse Two', 'type' => 'WAREHOUSE', 'operational_unit_id' => null],
        ]);

        $this->assertDatabaseCount('items', 2);
        $this->assertDatabaseCount('inventory_locations', 2);
    }

    public function test_deleting_an_actor_preserves_the_activity_log(): void
    {
        $actor = User::factory()->create();
        $logId = DB::table('activity_logs')->insertGetId([
            'actor_id' => $actor->id,
            'action' => 'UNIT_PRICE_CHANGED',
            'subject_type' => 'unit_item',
            'subject_id' => 123,
            'metadata' => json_encode(['old_price' => '5000.00', 'new_price' => '6000.00']),
        ]);

        $actor->delete();

        $this->assertDatabaseHas('activity_logs', [
            'id' => $logId,
            'actor_id' => null,
            'action' => 'UNIT_PRICE_CHANGED',
            'subject_id' => 123,
        ]);
    }

    public function test_a_transaction_prevents_deleting_its_creator(): void
    {
        $creator = User::factory()->create();
        $unitId = DB::table('operational_units')->insertGetId([
            'code' => 'UNIT-1', 'name' => 'Unit One', 'type' => 'OUTLET',
        ]);
        DB::table('transactions')->insert([
            'transaction_number' => 'TRX-1',
            'operational_unit_id' => $unitId,
            'status' => 'PAID',
            'created_by' => $creator->id,
        ]);

        $this->expectException(QueryException::class);

        $creator->delete();
    }
}
