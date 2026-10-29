<?php

namespace App\Http\Controllers\Api;

use App\Enums\ForecastDecisionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreForecastResultDecisionRequest;
use App\Http\Resources\ForecastRunResource;
use App\Models\ForecastResult;
use App\Models\ForecastRun;
use App\Models\User;
use App\Services\ForecastDecisionService;
use Illuminate\Http\JsonResponse;

class ForecastResultDecisionController extends Controller
{
    public function store(
        StoreForecastResultDecisionRequest $request,
        ForecastResult $forecastResult,
        ForecastDecisionService $decisions,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        $attributes = $request->validated();

        $decisions->decide(
            $user,
            $forecastResult,
            ForecastDecisionStatus::from($attributes['status']),
            $attributes['final_restock_quantity'] ?? null,
            $attributes['reason'] ?? null,
        );

        $run = ForecastRun::query()
            ->with(['requester', 'operationalUnit', 'results.item', 'results.decisions.decisionMaker'])
            ->findOrFail($forecastResult->forecast_run_id);

        return (new ForecastRunResource($run))->response()->setStatusCode(201);
    }
}
