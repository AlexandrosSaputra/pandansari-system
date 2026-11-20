<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\OperationalUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
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
            'forecast.prediction.provider' => 'local',
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
            'safety_stock_mode' => 'CALCULATED',
            'target_service_level' => 95,
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
        $this->assertTrue($results->every(
            fn (array $result): bool => $result['decision_status'] === 'PENDING'
                && $result['final_restock_quantity'] === null
                && $result['decision_history'] === [],
        ));
        $this->assertTrue($results->every(function (array $result): bool {
            $unrounded = max(
                0,
                $result['predicted_units'] + $result['safety_stock'] - $result['available_quantity'],
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
        $this->assertSame('CALCULATED', $packagedResult['safety_stock_mode']);
        $this->assertSame(95, $packagedResult['target_service_level']);
        $this->assertSame($packagedResult['calculated_safety_stock'], $packagedResult['safety_stock']);
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

    public function test_options_report_ollama_ready_only_when_the_configured_model_is_installed(): void
    {
        $superadmin = User::query()->where('username', 'admin')->firstOrFail();
        config([
            'forecast.explanation.provider' => 'ollama',
            'forecast.explanation.ollama.base_url' => 'http://ollama.test',
            'forecast.explanation.ollama.model' => 'llama3.2',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'http://ollama.test/api/tags' => Http::sequence()
                ->push(['models' => [['name' => 'llama3.2:latest']]])
                ->push(['models' => [['name' => 'other:latest']]])
                ->push(['error' => 'unavailable'], 503),
        ]);
        $this->actingAs($superadmin, 'web');

        $this->getJson('/api/forecasts/options')
            ->assertOk()
            ->assertJsonPath('data.explanation_runtime.status', 'ready')
            ->assertJsonPath('data.explanation_runtime.model', 'llama3.2');
        $this->getJson('/api/forecasts/options')
            ->assertOk()
            ->assertJsonPath('data.explanation_runtime.status', 'model_missing');
        $this->getJson('/api/forecasts/options')
            ->assertOk()
            ->assertJsonPath('data.explanation_runtime.status', 'unavailable');
        Http::assertSentCount(3);
    }

    public function test_options_report_ollama_unavailable_on_connection_failure(): void
    {
        $superadmin = User::query()->where('username', 'admin')->firstOrFail();
        config([
            'forecast.explanation.provider' => 'ollama',
            'forecast.explanation.ollama.base_url' => 'http://ollama.test',
            'forecast.explanation.ollama.model' => 'qwen2.5:3b',
        ]);
        Http::preventStrayRequests();
        Http::fake(['http://ollama.test/api/tags' => Http::failedConnection()]);
        $this->actingAs($superadmin, 'web');

        $this->getJson('/api/forecasts/options')
            ->assertOk()
            ->assertJsonPath('data.explanation_runtime.status', 'unavailable')
            ->assertJsonPath('data.explanation_runtime.model', 'qwen2.5:3b');
    }

    public function test_real_ollama_generates_a_forecast_explanation(): void
    {
        if (getenv('RUN_OLLAMA_INTEGRATION') !== '1') {
            $this->markTestSkipped('Set RUN_OLLAMA_INTEGRATION=1 and start Ollama with qwen2.5:3b to run this test.');
        }

        $superadmin = User::query()->where('username', 'admin')->firstOrFail();
        $unit = OperationalUnit::query()->where('name', 'Outlet 1')->firstOrFail();
        config([
            'forecast.explanation.provider' => 'ollama',
            'forecast.explanation.ollama.base_url' => 'http://127.0.0.1:11434',
            'forecast.explanation.ollama.model' => 'qwen2.5:3b',
            'forecast.explanation.timeout' => 300,
        ]);
        $this->actingAs($superadmin, 'web');

        $this->getJson('/api/forecasts/options')
            ->assertOk()
            ->assertJsonPath('data.explanation_runtime.status', 'ready')
            ->assertJsonPath('data.explanation_runtime.model', 'qwen2.5:3b');

        $response = $this->postJson('/api/forecasts', [
            'operational_unit_id' => $unit->id,
            'horizon' => 'next_week',
        ])->assertCreated()
            ->assertJsonPath('data.explanation_provider', 'ollama')
            ->assertJsonPath('data.explanation_status', 'generated')
            ->assertJsonCount(9, 'data.results');

        $this->assertNotEmpty($response->json('data.explanation.summary'));
        $this->assertStringContainsString('rekomendasi restock total', $response->json('data.explanation.summary'));
        $this->assertIsArray($response->json('data.explanation.risks'));
        $this->assertIsArray($response->json('data.explanation.actions'));
    }

    public function test_forecast_uses_system_explanation_when_ollama_fails(): void
    {
        $superadmin = User::query()->where('username', 'admin')->firstOrFail();
        $unit = OperationalUnit::query()->where('name', 'Outlet 1')->firstOrFail();
        config([
            'forecast.explanation.provider' => 'ollama',
            'forecast.explanation.ollama.base_url' => 'http://ollama.test',
        ]);
        Http::preventStrayRequests();
        Http::fake(['http://ollama.test/api/chat' => Http::response(['error' => 'unavailable'], 503)]);
        $this->actingAs($superadmin, 'web');

        $response = $this->postJson('/api/forecasts', [
            'operational_unit_id' => $unit->id,
            'horizon' => 'next_week',
        ])->assertCreated()
            ->assertJsonPath('data.explanation_provider', 'ollama')
            ->assertJsonPath('data.explanation_status', 'fallback')
            ->assertJsonCount(9, 'data.results');

        $this->assertNotEmpty($response->json('data.explanation.summary'));
        $this->assertDatabaseHas('forecast_runs', [
            'id' => $response->json('data.id'),
            'explanation_provider' => 'ollama',
            'explanation_status' => 'fallback',
        ]);
        Http::assertSentCount(1);
    }

    public function test_ai_service_predictions_are_used_after_explicit_activation(): void
    {
        $superadmin = User::query()->where('username', 'admin')->firstOrFail();
        $unit = OperationalUnit::query()->where('name', 'Outlet 1')->firstOrFail();
        config([
            'forecast.prediction.provider' => 'ai_service',
            'forecast.prediction.ai_service.base_url' => 'http://ai-service.test',
            'forecast.prediction.ai_service.timeout' => 2,
        ]);
        Http::fake([
            'http://ai-service.test/v1/forecasts/weekly' => function (Request $request) {
                $lastWeek = CarbonImmutable::parse(collect($request['week_starts'])->last());
                $predictions = collect(range(1, $request['steps']))
                    ->map(fn (int $step): array => [
                        'week_start' => $lastWeek->addWeeks($step)->toDateString(),
                        'units' => 12.0,
                    ])->all();

                return Http::response([
                    'model_version' => 'extra_trees_v1',
                    'trained_through' => '2026-08-24',
                    'evaluation_metrics' => [
                        'mae' => 4.1207,
                        'rmse' => 8.3719,
                        'wape' => 17.7555,
                        'observations' => 1508,
                    ],
                    'predictions' => $predictions,
                ]);
            },
        ]);
        $this->actingAs($superadmin, 'web');

        $response = $this->postJson('/api/forecasts', [
            'operational_unit_id' => $unit->id,
            'horizon' => 'next_week',
        ])->assertCreated()
            ->assertJsonPath('data.method', 'extra_trees_ai_service_v1')
            ->assertJsonPath('data.metrics.prediction_provider_requested', 'ai_service')
            ->assertJsonPath('data.metrics.prediction_provider_counts.ai_service', 9)
            ->assertJsonPath('data.metrics.prediction_provider_counts.local_fallback', 0)
            ->assertJsonPath('data.metrics.metric_source', 'extra_trees_test_set')
            ->assertJsonPath('data.metrics.wape', 17.7555);

        $this->assertEqualsCanonicalizing(
            array_fill(0, 9, 'extra_trees_v1'),
            collect($response->json('data.results'))->pluck('model')->all(),
        );
        $this->assertEqualsCanonicalizing(
            array_fill(0, 9, 12),
            collect($response->json('data.results'))->pluck('predicted_units')->all(),
        );
        $this->assertEqualsCanonicalizing(
            array_fill(0, 9, 8.3719),
            collect($response->json('data.results'))->pluck('rmse')->all(),
        );
        Http::assertSentCount(9);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://ai-service.test/v1/forecasts/weekly'
            && $request['outlet'] === 'Baso'
            && count($request['week_starts']) === 208
            && count($request['units_sold']) === 208
            && $request['steps'] === 10);
    }

    public function test_ai_service_failure_falls_back_without_failing_the_forecast(): void
    {
        $superadmin = User::query()->where('username', 'admin')->firstOrFail();
        $unit = OperationalUnit::query()->where('name', 'Outlet 1')->firstOrFail();
        config([
            'forecast.prediction.provider' => 'ai_service',
            'forecast.prediction.ai_service.base_url' => 'http://ai-service.test',
        ]);
        Http::fake([
            'http://ai-service.test/*' => Http::response(['detail' => 'unavailable'], 503),
        ]);
        $this->actingAs($superadmin, 'web');

        $response = $this->postJson('/api/forecasts', [
            'operational_unit_id' => $unit->id,
            'horizon' => 'next_week',
        ])->assertCreated()
            ->assertJsonPath('data.method', 'local_time_series_ai_fallback_v1')
            ->assertJsonPath('data.metrics.prediction_provider_requested', 'ai_service')
            ->assertJsonPath('data.metrics.prediction_provider_counts.ai_service', 0)
            ->assertJsonPath('data.metrics.prediction_provider_counts.local_fallback', 9);

        $this->assertTrue(collect($response->json('data.results'))->every(
            fn (array $result): bool => in_array($result['model'], [
                'lag_1',
                'moving_average_4',
                'seasonal_52',
                'seasonal_average_3',
            ], true),
        ));
        Http::assertSentCount(1);
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
                    'content' => json_encode([
                        'risk_codes' => ['stockouts'],
                        'action_codes' => ['review_history'],
                    ], JSON_THROW_ON_ERROR),
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

        $this->assertStringContainsString('rekomendasi restock total', $ollama['explanation']['summary']);
        $this->assertContains('Penjualan saat stok habis mungkin tidak mencerminkan seluruh permintaan.', $ollama['explanation']['risks']);
        $this->assertContains('Periksa kelengkapan dan ketepatan data penjualan historis.', $ollama['explanation']['actions']);

        $this->assertSame(
            collect($openAi['results'])->pluck('predicted_units')->all(),
            collect($ollama['results'])->pluck('predicted_units')->all(),
        );
        $this->assertSame(
            collect($openAi['results'])->pluck('recommended_restock')->all(),
            collect($ollama['results'])->pluck('recommended_restock')->all(),
        );
    }

    public function test_ollama_unknown_codes_use_safe_fallback(): void
    {
        $superadmin = User::query()->where('username', 'admin')->firstOrFail();
        config([
            'forecast.explanation.provider' => 'ollama',
            'forecast.explanation.ollama.base_url' => 'http://ollama.test',
        ]);
        Http::preventStrayRequests();
        Http::fake(['http://ollama.test/api/chat' => Http::response([
            'message' => ['content' => json_encode([
                'risk_codes' => ['WAPE tertinggi 22.3364'],
                'action_codes' => ['review_history'],
            ], JSON_THROW_ON_ERROR)],
        ])]);
        $this->actingAs($superadmin, 'web');

        $response = $this->postJson('/api/forecasts', [
            'operational_unit_id' => OperationalUnit::query()->where('name', 'Outlet 1')->value('id'),
            'horizon' => 'next_week',
        ])->assertCreated()->assertJsonPath('data.explanation_status', 'fallback');

        $this->assertStringNotContainsString('WAPE tertinggi 22.3364', json_encode($response->json('data.explanation')));
        $this->assertStringContainsString('rekomendasi restock total', $response->json('data.explanation.summary'));
        Http::assertSentCount(1);
    }
}
