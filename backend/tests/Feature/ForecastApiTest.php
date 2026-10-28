<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\OperationalUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ForecastApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-10-27 10:00:00');
        config([
            'demo.user_password' => 'DemoPassword123!',
            'forecast.explanation.provider' => 'none',
        ]);
        $this->seed(DemoDataSeeder::class);
        $this->assertSame(0, Artisan::call('forecast:import-sales'), Artisan::output());
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_superadmin_generates_a_forecast_without_mutating_inventory(): void
    {
        $superadmin = User::query()->where('username', 'admin')->firstOrFail();
        $unit = OperationalUnit::query()->where('name', 'Outlet 1')->firstOrFail();
        $location = $unit->inventoryLocation()->firstOrFail();
        $packagedItem = $unit->unitItems()
            ->whereHas('item', fn ($query) => $query->where('name', 'BENG-BENG'))
            ->firstOrFail();
        $packagedItem->update([
            'supplier_lead_time_days' => 7,
            'order_pack_size' => 6,
        ]);
        $balancesBefore = InventoryBalance::query()
            ->where('inventory_location_id', $location->id)
            ->pluck('quantity_on_hand', 'item_id')
            ->all();
        $movementCount = $location->stockMovements()->count();
        $this->actingAs($superadmin, 'web');

        $this->getJson('/api/forecasts/options')
            ->assertOk()
            ->assertJsonCount(3, 'data.units')
            ->assertJsonPath('data.units.0.data_freshness.status', 'stale')
            ->assertJsonPath('data.units.0.data_freshness.age_days', 58)
            ->assertJsonPath('data.explanation_provider', 'none');

        $response = $this->postJson('/api/forecasts', [
            'operational_unit_id' => $unit->id,
            'horizon' => 'next_week',
        ])->assertCreated()
            ->assertJsonPath('data.operational_unit.id', $unit->id)
            ->assertJsonPath('data.forecast_start', '2026-11-02')
            ->assertJsonPath('data.forecast_end', '2026-11-08')
            ->assertJsonPath('data.history_through', '2026-08-30')
            ->assertJsonPath('data.data_freshness.status', 'stale')
            ->assertJsonPath('data.data_freshness.age_days', 58)
            ->assertJsonPath('data.data_freshness.gap_weeks', 9)
            ->assertJsonPath('data.data_freshness.requires_warning', true)
            ->assertJsonPath('data.method', 'per_sku_time_series_selection_v2')
            ->assertJsonPath('data.explanation_status', 'disabled')
            ->assertJsonCount(9, 'data.results');

        $this->assertStringContainsString(
            'Data terakhir berumur 58 hari',
            $response->json('data.explanation.risks.0'),
        );

        $results = collect($response->json('data.results'));
        $this->assertTrue($results->every(function (array $result): bool {
            $unrounded = max(
                0,
                $result['predicted_units'] + $result['minimum_stock'] - $result['available_quantity'],
            );
            $rounded = $unrounded === 0
                ? 0
                : (int) (ceil($unrounded / $result['order_pack_size']) * $result['order_pack_size']);

            return $result['unrounded_restock'] === $unrounded
                && $result['recommended_restock'] === $rounded;
        }));
        $this->assertTrue($results->every(
            fn (array $result): bool => in_array($result['model'], [
                'lag_1',
                'moving_average_4',
                'seasonal_52',
                'seasonal_average_3',
            ], true),
        ));
        $this->assertEqualsCanonicalizing(
            ['lag_1', 'moving_average_4', 'seasonal_52', 'seasonal_average_3'],
            array_keys($response->json('data.metrics.candidate_metrics')),
        );
        $packagedResult = $results->firstWhere('item.name', 'BENG-BENG');
        $this->assertSame(6, $packagedResult['order_pack_size']);
        $this->assertSame(7, $packagedResult['supplier_lead_time_days']);
        $this->assertSame('2026-10-26', $packagedResult['order_by_date']);
        $this->assertSame('overdue', $packagedResult['order_status']);
        $this->assertSame(0, $packagedResult['recommended_restock'] % 6);
        $this->assertDatabaseCount('forecast_runs', 1);
        $this->assertDatabaseCount('forecast_results', 9);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $superadmin->id,
            'action' => 'FORECAST_GENERATED',
        ]);
        $this->assertSame($movementCount, $location->stockMovements()->count());
        $this->assertSame(
            $balancesBefore,
            InventoryBalance::query()
                ->where('inventory_location_id', $location->id)
                ->pluck('quantity_on_hand', 'item_id')
                ->all(),
        );
    }

    public function test_forecast_endpoints_are_superadmin_only_even_when_warehouse_has_ai_permissions(): void
    {
        $warehouse = User::query()->where('username', 'gudang')->firstOrFail();
        $this->actingAs($warehouse, 'web');

        $this->getJson('/api/forecasts/options')->assertForbidden();
        $this->postJson('/api/forecasts', [
            'operational_unit_id' => OperationalUnit::query()->where('name', 'Outlet 1')->value('id'),
            'horizon' => 'next_week',
        ])->assertForbidden();
    }

    public function test_custom_forecast_requires_a_future_target_date(): void
    {
        $superadmin = User::query()->where('username', 'admin')->firstOrFail();
        $this->actingAs($superadmin, 'web');

        $this->postJson('/api/forecasts', [
            'operational_unit_id' => OperationalUnit::query()->where('name', 'Outlet 1')->value('id'),
            'horizon' => 'custom',
            'target_date' => '2026-10-27',
        ])->assertUnprocessable()->assertJsonValidationErrors('target_date');
    }

    public function test_openai_and_ollama_can_generate_structured_explanations_without_changing_forecast_numbers(): void
    {
        $superadmin = User::query()->where('username', 'admin')->firstOrFail();
        $unitId = OperationalUnit::query()->where('name', 'Outlet 1')->value('id');
        $structured = [
            'summary' => 'Ringkasan terstruktur.',
            'risks' => ['Risiko satu.'],
            'actions' => ['Tinjau stok fisik.'],
        ];
        $this->actingAs($superadmin, 'web');

        config([
            'forecast.explanation.provider' => 'openai',
            'forecast.explanation.openai.api_key' => 'test-key',
            'forecast.explanation.openai.base_url' => 'https://api.openai.test/v1',
            'forecast.explanation.openai.model' => 'test-model',
        ]);
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode($structured, JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);
        $openAi = $this->postJson('/api/forecasts', [
            'operational_unit_id' => $unitId,
            'horizon' => 'next_week',
        ])->assertCreated()
            ->assertJsonPath('data.explanation_provider', 'openai')
            ->assertJsonPath('data.explanation_status', 'generated')
            ->assertJsonPath('data.explanation.summary', $structured['summary'])
            ->json('data');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.test/v1/responses'
            && $request['store'] === false
            && $request['text']['format']['type'] === 'json_schema'
            && $request['text']['format']['strict'] === true);

        config([
            'forecast.explanation.provider' => 'ollama',
            'forecast.explanation.ollama.base_url' => 'http://ollama.test',
            'forecast.explanation.ollama.model' => 'test-model',
        ]);
        Http::fake([
            'http://ollama.test/api/chat' => Http::response([
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode($structured, JSON_THROW_ON_ERROR),
                ],
            ]),
        ]);
        $ollama = $this->postJson('/api/forecasts', [
            'operational_unit_id' => $unitId,
            'horizon' => 'next_week',
        ])->assertCreated()
            ->assertJsonPath('data.explanation_provider', 'ollama')
            ->assertJsonPath('data.explanation_status', 'generated')
            ->json('data');
        Http::assertSent(fn ($request): bool => $request->url() === 'http://ollama.test/api/chat'
            && $request['stream'] === false
            && $request['format']['type'] === 'object'
            && $request['options']['temperature'] === 0);

        $this->assertSame(
            collect($openAi['results'])->pluck('predicted_units')->all(),
            collect($ollama['results'])->pluck('predicted_units')->all(),
        );
        $this->assertSame(
            collect($openAi['results'])->pluck('recommended_restock')->all(),
            collect($ollama['results'])->pluck('recommended_restock')->all(),
        );
    }
}
