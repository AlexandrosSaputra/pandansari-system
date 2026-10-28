from __future__ import annotations

import os
from datetime import date
from functools import lru_cache
from pathlib import Path
from typing import Any

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

from app.artifact import load_artifact
from app.evaluation import evaluate_dataset
from app.inference import forecast_weekly

ROOT = Path(__file__).resolve().parents[2]
DEFAULT_DATASET = ROOT / "backend" / "database" / "data" / "historical-sales.csv"
DEFAULT_ARTIFACT = ROOT / "ai-service" / "artifacts" / "extra_trees_v1.pkl.gz"

app = FastAPI(title="Pandansari AI Service", version="0.1.0")


class WeeklyForecastRequest(BaseModel):
    outlet: str = Field(min_length=1, max_length=255)
    sku: str = Field(min_length=1, max_length=255)
    week_starts: list[date] = Field(min_length=52)
    units_sold: list[float] = Field(min_length=52)
    steps: int = Field(ge=1, le=104)


def artifact_path() -> Path:
    return Path(os.getenv("FORECAST_MODEL_PATH", DEFAULT_ARTIFACT)).resolve()


@lru_cache(maxsize=4)
def cached_artifact(path: str, modified_at_ns: int) -> dict[str, Any]:
    del modified_at_ns
    return load_artifact(Path(path))


def current_artifact() -> dict[str, Any]:
    path = artifact_path()
    try:
        return cached_artifact(str(path), path.stat().st_mtime_ns)
    except (OSError, ValueError) as error:
        raise HTTPException(status_code=503, detail=f"Forecast model is unavailable: {error}") from error


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "service": "pandansari-ai"}


@app.get("/v1/models/current")
def current_model() -> dict[str, Any]:
    return current_artifact()["metadata"]


@app.post("/v1/forecasts/weekly")
def weekly_forecast(request: WeeklyForecastRequest) -> dict[str, Any]:
    artifact = current_artifact()
    try:
        predictions = forecast_weekly(
            artifact,
            request.outlet,
            request.sku,
            request.week_starts,
            request.units_sold,
            request.steps,
        )
    except ValueError as error:
        raise HTTPException(status_code=422, detail=str(error)) from error

    return {
        "model_version": artifact["metadata"]["model_version"],
        "trained_through": artifact["metadata"]["trained_through"],
        "evaluation_metrics": artifact["metadata"]["evaluation"]["test"],
        "predictions": predictions,
    }


@app.post("/experiments/tree-candidates/evaluate")
def evaluate_tree_candidates() -> dict[str, Any]:
    dataset = Path(os.getenv("HISTORICAL_SALES_PATH", DEFAULT_DATASET)).resolve()
    threshold = float(os.getenv("MINIMUM_WAPE_IMPROVEMENT", "2.0"))
    return evaluate_dataset(dataset, threshold)


@app.post("/experiments/random-forest/evaluate", deprecated=True)
def evaluate_random_forest() -> dict[str, Any]:
    return evaluate_tree_candidates()
