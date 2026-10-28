from __future__ import annotations

import math
from datetime import date, timedelta
from typing import Any

from app.evaluation import WeeklySale, feature_row


def forecast_weekly(
    artifact: dict[str, Any],
    outlet: str,
    sku: str,
    week_starts: list[date],
    units_sold: list[float],
    steps: int,
) -> list[dict[str, Any]]:
    if len(week_starts) != len(units_sold):
        raise ValueError("week_starts and units_sold must have equal lengths.")
    if len(week_starts) < 52:
        raise ValueError("At least 52 contiguous weeks are required for inference.")
    if not 1 <= steps <= 104:
        raise ValueError("steps must be between 1 and 104.")
    if any(not math.isfinite(value) or value < 0 for value in units_sold):
        raise ValueError("units_sold must contain finite non-negative values.")
    for previous, current in zip(week_starts, week_starts[1:]):
        if current - previous != timedelta(days=7):
            raise ValueError("week_starts must be weekly and contiguous.")

    series = [
        WeeklySale(week_start=week_start, outlet=outlet, sku=sku, units_sold=float(units))
        for week_start, units in zip(week_starts, units_sold)
    ]
    model = artifact["model"]
    predictions: list[dict[str, Any]] = []

    for _ in range(steps):
        next_week = series[-1].week_start + timedelta(days=7)
        index = len(series)
        series.append(WeeklySale(next_week, outlet, sku, 0.0))
        prediction = max(0.0, float(model.predict([feature_row(series, index)])[0]))
        series[-1] = WeeklySale(next_week, outlet, sku, prediction)
        predictions.append({
            "week_start": next_week.isoformat(),
            "units": round(prediction, 4),
        })

    return predictions
