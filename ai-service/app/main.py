from __future__ import annotations

import os
from pathlib import Path
from typing import Any

from fastapi import FastAPI

from app.evaluation import evaluate_dataset

ROOT = Path(__file__).resolve().parents[2]
DEFAULT_DATASET = ROOT / "backend" / "database" / "data" / "historical-sales.csv"

app = FastAPI(title="Pandansari AI Service", version="0.1.0")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "service": "pandansari-ai"}


@app.post("/experiments/random-forest/evaluate")
def evaluate_random_forest() -> dict[str, Any]:
    dataset = Path(os.getenv("HISTORICAL_SALES_PATH", DEFAULT_DATASET)).resolve()
    threshold = float(os.getenv("MINIMUM_WAPE_IMPROVEMENT", "2.0"))
    return evaluate_dataset(dataset, threshold)
