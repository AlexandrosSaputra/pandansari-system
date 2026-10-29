<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateForecastRequest;
use App\Http\Resources\ForecastRunResource;
use App\Models\ForecastRun;
use App\Models\HistoricalSale;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ForecastExplanationService;
use App\Services\ForecastService;
use App\Support\ForecastDataFreshness;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ForecastController extends Controller
{
    public function options(
        ForecastDataFreshness $freshness,
        ForecastExplanationService $explanations,
    ): JsonResponse {
        $units = HistoricalSale::query()
            ->join('operational_units', 'operational_units.id', '=', 'historical_sales.operational_unit_id')
            ->select('operational_units.id', 'operational_units.code', 'operational_units.name')
            ->selectRaw('MIN(historical_sales.week_start) as history_start')
            ->selectRaw('MAX(historical_sales.week_end) as history_end')
            ->selectRaw('COUNT(DISTINCT historical_sales.week_start) as history_weeks')
            ->selectRaw('COUNT(DISTINCT historical_sales.item_id) as product_count')
            ->where('operational_units.is_active', true)
            ->groupBy('operational_units.id', 'operational_units.code', 'operational_units.name')
            ->orderBy('operational_units.name')
            ->get()
            ->map(function ($unit) use ($freshness): array {
                $historyEnd = CarbonImmutable::parse($unit->history_end, config('forecast.timezone'));
                $dataFreshness = $freshness->assess($historyEnd, $historyEnd->addDay());

                return [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'history_start' => $unit->history_start,
                    'history_end' => $unit->history_end,
                    'history_weeks' => (int) $unit->history_weeks,
                    'product_count' => (int) $unit->product_count,
                    'data_freshness' => $dataFreshness,
                ];
            });

        return response()->json([
            'data' => [
                'units' => $units,
                'explanation_provider' => config('forecast.explanation.provider', 'none'),
                'explanation_runtime' => $explanations->runtimeStatus(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'operational_unit_id' => ['sometimes', 'integer', 'exists:operational_units,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $runs = ForecastRun::query()
            ->with(['requester', 'operationalUnit', 'results.item', 'results.decisions.decisionMaker'])
            ->when($filters['operational_unit_id'] ?? null, fn ($query, int $unitId) => $query
                ->where('operational_unit_id', $unitId))
            ->latest()
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        return ForecastRunResource::collection($runs);
    }

    public function store(
        GenerateForecastRequest $request,
        ForecastService $forecasts,
        ForecastExplanationService $explanations,
        ActivityLogger $logger,
    ): ForecastRunResource {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        $run = $forecasts->generate($user, $request->validated());
        $run = $explanations->explain($run)->load([
            'requester',
            'operationalUnit',
            'results.item',
            'results.decisions.decisionMaker',
        ]);
        $logger->log($user, 'FORECAST_GENERATED', $run, [
            'operational_unit_id' => $run->operational_unit_id,
            'horizon' => $run->horizon,
            'forecast_start' => $run->forecast_start->toDateString(),
            'forecast_end' => $run->forecast_end->toDateString(),
            'recommended_restock' => $run->results->sum('recommended_restock'),
            'explanation_provider' => $run->explanation_provider,
            'explanation_status' => $run->explanation_status,
        ]);

        return new ForecastRunResource($run);
    }

    public function show(ForecastRun $forecastRun): ForecastRunResource
    {
        return new ForecastRunResource($forecastRun->load([
            'requester',
            'operationalUnit',
            'results.item',
            'results.decisions.decisionMaker',
        ]));
    }
}
