<?php

namespace Tests\Feature;

use App\Models\ForecastResult;
use App\Models\ForecastRun;
use App\Models\InventoryBalance;
use App\Models\OperationalUnit;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ForecastResultDecisionApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.user_password' => 'DemoPassword123!']);
        $this->seed(DemoDataSeeder::class);
    }

    public function test_superadmin_approves_a_recommendation_without_mutating_stock(): void
    {
        [$superadmin, $result] = $this->forecastResult();
        $balancesBefore = InventoryBalance::query()->orderBy('id')->get()->toArray();
        $movementCountBefore = StockMovement::query()->count();
        $this->actingAs($superadmin, 'web');

        $this->postJson("/api/forecasts/results/{$result->id}/decisions", [
            'status' => 'APPROVED',
            'final_restock_quantity' => 18,
            'unexpected_attribute' => 'ignored',
        ])->assertCreated()
            ->assertJsonPath('data.results.0.decision_status', 'APPROVED')
            ->assertJsonPath('data.results.0.final_restock_quantity', 18)
            ->assertJsonPath('data.results.0.decision_reason', null)
            ->assertJsonPath('data.results.0.decided_by.id', $superadmin->id)
            ->assertJsonCount(1, 'data.results.0.decision_history')
            ->assertJsonPath('data.results.0.decision_history.0.recommended_restock_snapshot', 18);

        $this->assertDatabaseHas('forecast_result_decisions', [
            'forecast_result_id' => $result->id,
            'status' => 'APPROVED',
            'recommended_restock_snapshot' => 18,
            'final_restock_quantity' => 18,
            'reason' => null,
            'decided_by' => $superadmin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $superadmin->id,
            'action' => 'FORECAST_RECOMMENDATION_APPROVED',
            'subject_type' => 'ForecastResultDecision',
        ]);
        $this->assertSame($movementCountBefore, StockMovement::query()->count());
        $this->assertSame($balancesBefore, InventoryBalance::query()->orderBy('id')->get()->toArray());
    }

    public function test_corrected_approval_returns_422_without_a_reason(): void
    {
        [$superadmin, $result] = $this->forecastResult();
        $this->actingAs($superadmin, 'web');

        $this->postJson("/api/forecasts/results/{$result->id}/decisions", [
            'status' => 'APPROVED',
            'final_restock_quantity' => 12,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('reason')
            ->assertJsonPath('errors.reason.0', 'Alasan wajib diisi ketika jumlah restock dikoreksi.');

        $this->assertDatabaseCount('forecast_result_decisions', 0);
    }

    public function test_rejection_returns_422_without_a_reason(): void
    {
        [$superadmin, $result] = $this->forecastResult();
        $this->actingAs($superadmin, 'web');

        $this->postJson("/api/forecasts/results/{$result->id}/decisions", [
            'status' => 'REJECTED',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseCount('forecast_result_decisions', 0);
    }

    public function test_pending_status_returns_422_because_it_is_not_a_decision(): void
    {
        [$superadmin, $result] = $this->forecastResult();
        $this->actingAs($superadmin, 'web');

        $this->postJson("/api/forecasts/results/{$result->id}/decisions", [
            'status' => 'PENDING',
            'final_restock_quantity' => 18,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseCount('forecast_result_decisions', 0);
    }

    public function test_new_decision_becomes_current_and_preserves_the_previous_history(): void
    {
        [$superadmin, $result] = $this->forecastResult();
        $this->actingAs($superadmin, 'web');
        $this->travelTo('2026-10-29 10:00:00');
        $this->postJson("/api/forecasts/results/{$result->id}/decisions", [
            'status' => 'REJECTED',
            'reason' => 'Stok fisik masih mencukupi.',
        ])->assertCreated();
        $this->travelTo('2026-10-29 11:00:00');

        $this->postJson("/api/forecasts/results/{$result->id}/decisions", [
            'status' => 'APPROVED',
            'final_restock_quantity' => 12,
            'reason' => 'Disesuaikan dengan kapasitas rak.',
        ])->assertCreated()
            ->assertJsonPath('data.results.0.decision_status', 'APPROVED')
            ->assertJsonPath('data.results.0.final_restock_quantity', 12)
            ->assertJsonCount(2, 'data.results.0.decision_history')
            ->assertJsonPath('data.results.0.decision_history.0.status', 'APPROVED')
            ->assertJsonPath('data.results.0.decision_history.1.status', 'REJECTED')
            ->assertJsonPath('data.results.0.decision_history.1.final_restock_quantity', null);

        $this->assertDatabaseCount('forecast_result_decisions', 2);
    }

    public function test_forecast_decision_endpoint_forbids_non_superadmin_users(): void
    {
        [, $result] = $this->forecastResult();
        $warehouse = User::query()->where('username', 'gudang')->firstOrFail();
        $this->actingAs($warehouse, 'web');

        $this->postJson("/api/forecasts/results/{$result->id}/decisions", [
            'status' => 'APPROVED',
            'final_restock_quantity' => 18,
        ])->assertForbidden();

        $this->assertDatabaseCount('forecast_result_decisions', 0);
    }

    /** @return array{User, ForecastResult} */
    private function forecastResult(): array
    {
        $superadmin = User::query()->where('username', 'admin')->firstOrFail();
        $unit = OperationalUnit::query()->where('name', 'Outlet 1')->firstOrFail();
        $item = $unit->unitItems()->firstOrFail()->item;
        $run = ForecastRun::factory()->create([
            'requested_by' => $superadmin->id,
            'operational_unit_id' => $unit->id,
        ]);
        $result = ForecastResult::factory()->create([
            'forecast_run_id' => $run->id,
            'operational_unit_id' => $unit->id,
            'item_id' => $item->id,
            'recommended_restock' => 18,
            'unrounded_restock' => 17,
        ]);

        return [$superadmin, $result];
    }
}
