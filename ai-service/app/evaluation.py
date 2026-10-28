from __future__ import annotations

import csv
import math
from collections import defaultdict
from dataclasses import dataclass
from datetime import date, timedelta
from pathlib import Path
from typing import Any, Iterable

import numpy as np
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import RandomForestRegressor
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

HOLDOUT_WEEKS = 52
MINIMUM_HISTORY_WEEKS = 156
BASELINE_MODELS = ("lag_1", "moving_average_4", "seasonal_52")
FEATURE_NAMES = (
    "outlet",
    "sku",
    "lag_1",
    "lag_2",
    "lag_4",
    "rolling_4",
    "rolling_13",
    "rolling_52",
    "lag_52",
    "week_sin",
    "week_cos",
    "trend_index",
)


@dataclass(frozen=True)
class WeeklySale:
    week_start: date
    outlet: str
    sku: str
    units_sold: float


@dataclass(frozen=True)
class ModelMetrics:
    mae: float
    rmse: float
    wape: float
    observations: int

    def as_dict(self) -> dict[str, float | int]:
        return {
            "mae": round(self.mae, 4),
            "rmse": round(self.rmse, 4),
            "wape": round(self.wape, 4),
            "observations": self.observations,
        }


def load_sales(path: Path) -> dict[tuple[str, str], list[WeeklySale]]:
    required = {"week_start", "outlet", "sku", "units_sold"}
    grouped: dict[tuple[str, str], list[WeeklySale]] = defaultdict(list)

    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        if reader.fieldnames is None or not required.issubset(reader.fieldnames):
            raise ValueError("Historical sales CSV is missing required columns.")

        for row in reader:
            sale = WeeklySale(
                week_start=date.fromisoformat(row["week_start"]),
                outlet=row["outlet"].strip(),
                sku=row["sku"].strip(),
                units_sold=float(row["units_sold"]),
            )
            grouped[(sale.outlet, sale.sku)].append(sale)

    if not grouped:
        raise ValueError("Historical sales CSV contains no observations.")

    for key, series in grouped.items():
        series.sort(key=lambda sale: sale.week_start)
        if len(series) < MINIMUM_HISTORY_WEEKS:
            raise ValueError(f"Series {key} has fewer than {MINIMUM_HISTORY_WEEKS} weeks.")
        if len({sale.week_start for sale in series}) != len(series):
            raise ValueError(f"Series {key} contains duplicate weeks.")
        for previous, current in zip(series, series[1:]):
            if (current.week_start - previous.week_start).days != 7:
                raise ValueError(f"Series {key} is not weekly and contiguous.")

    return dict(grouped)


def feature_row(series: list[WeeklySale], index: int) -> list[Any]:
    values = [sale.units_sold for sale in series]
    week = series[index].week_start.isocalendar().week
    angle = 2 * math.pi * week / 52
    return [
        series[index].outlet,
        series[index].sku,
        values[index - 1],
        values[index - 2],
        values[index - 4],
        sum(values[index - 4 : index]) / 4,
        sum(values[index - 13 : index]) / 13,
        sum(values[index - 52 : index]) / 52,
        values[index - 52],
        math.sin(angle),
        math.cos(angle),
        index,
    ]


def build_rows(
    series_by_key: dict[tuple[str, str], list[WeeklySale]],
    start: int,
    end: int,
) -> tuple[list[list[Any]], list[float], list[tuple[str, str, int]]]:
    features: list[list[Any]] = []
    targets: list[float] = []
    identities: list[tuple[str, str, int]] = []

    for (outlet, sku), series in sorted(series_by_key.items()):
        for index in range(max(52, start), min(end, len(series))):
            features.append(feature_row(series, index))
            targets.append(series[index].units_sold)
            identities.append((outlet, sku, index))

    return features, targets, identities


def tree_pipeline() -> Pipeline:
    preprocessing = ColumnTransformer(
        [("categorical", OneHotEncoder(handle_unknown="ignore"), [0, 1])],
        remainder="passthrough",
    )
    model = RandomForestRegressor(
        n_estimators=300,
        min_samples_leaf=3,
        max_features=0.8,
        random_state=42,
        n_jobs=1,
    )
    return Pipeline([("features", preprocessing), ("model", model)])


def baseline_prediction(values: list[float], index: int, model: str) -> float:
    if model == "lag_1":
        return values[index - 1]
    if model == "moving_average_4":
        return sum(values[index - 4 : index]) / 4
    if model == "seasonal_52":
        return values[index - 52]
    raise ValueError(f"Unsupported baseline model: {model}")


def metrics(actual: Iterable[float], predicted: Iterable[float]) -> ModelMetrics:
    actual_values = np.asarray(list(actual), dtype=float)
    predicted_values = np.maximum(0, np.asarray(list(predicted), dtype=float))
    if actual_values.size == 0 or actual_values.size != predicted_values.size:
        raise ValueError("Metric inputs must be non-empty and have equal length.")
    errors = actual_values - predicted_values
    absolute_error = np.abs(errors)
    denominator = max(1.0, float(np.abs(actual_values).sum()))
    return ModelMetrics(
        mae=float(absolute_error.mean()),
        rmse=float(np.sqrt(np.mean(np.square(errors)))),
        wape=float(100 * absolute_error.sum() / denominator),
        observations=int(actual_values.size),
    )


def select_baselines(
    series_by_key: dict[tuple[str, str], list[WeeklySale]],
    validation_start: int,
    test_start: int,
) -> tuple[dict[tuple[str, str], str], dict[str, dict[str, float | int]]]:
    selected: dict[tuple[str, str], str] = {}
    aggregate_actual: dict[str, list[float]] = defaultdict(list)
    aggregate_predictions: dict[str, list[float]] = defaultdict(list)

    for key, series in series_by_key.items():
        values = [sale.units_sold for sale in series]
        scored: list[tuple[float, float, str]] = []
        actual = values[validation_start:test_start]
        for model in BASELINE_MODELS:
            predictions = [baseline_prediction(values, index, model) for index in range(validation_start, test_start)]
            score = metrics(actual, predictions)
            scored.append((score.mae, score.rmse, model))
            aggregate_actual[model].extend(actual)
            aggregate_predictions[model].extend(predictions)
        selected[key] = min(scored)[2]

    validation_metrics = {
        model: metrics(aggregate_actual[model], aggregate_predictions[model]).as_dict()
        for model in BASELINE_MODELS
    }
    return selected, validation_metrics


def promotion_decision(
    baseline: ModelMetrics,
    tree: ModelMetrics,
    minimum_relative_wape_improvement: float,
) -> dict[str, Any]:
    relative_improvement = 100 * (baseline.wape - tree.wape) / max(baseline.wape, 1e-9)
    passed = relative_improvement >= minimum_relative_wape_improvement and tree.mae <= baseline.mae
    return {
        "passed": passed,
        "candidate": "random_forest",
        "relative_wape_improvement_percent": round(relative_improvement, 4),
        "minimum_required_improvement_percent": minimum_relative_wape_improvement,
        "reason": (
            "Random Forest passed the WAPE improvement gate without worsening MAE."
            if passed
            else "Random Forest did not pass both the WAPE improvement and MAE gates."
        ),
    }


def selected_baseline_predictions(
    series_by_key: dict[tuple[str, str], list[WeeklySale]],
    selected_baselines: dict[tuple[str, str], str],
    start: int,
    end: int,
) -> tuple[list[float], list[float]]:
    actual: list[float] = []
    predicted: list[float] = []
    for key, series in sorted(series_by_key.items()):
        values = [sale.units_sold for sale in series]
        model = selected_baselines[key]
        for index in range(start, end):
            actual.append(values[index])
            predicted.append(baseline_prediction(values, index, model))
    return actual, predicted


def evaluate_dataset(path: Path, minimum_relative_wape_improvement: float = 2.0) -> dict[str, Any]:
    series_by_key = load_sales(path)
    lengths = {len(series) for series in series_by_key.values()}
    if len(lengths) != 1:
        raise ValueError("All SKU series must cover the same number of weeks.")
    weeks = lengths.pop()
    validation_start = weeks - (2 * HOLDOUT_WEEKS)
    test_start = weeks - HOLDOUT_WEEKS
    if validation_start < 52:
        raise ValueError("Dataset does not contain enough history for train, validation, and test splits.")

    selected_baselines, validation_metrics = select_baselines(series_by_key, validation_start, test_start)

    candidate_training_features, candidate_training_targets, _ = build_rows(
        series_by_key,
        52,
        validation_start,
    )
    validation_features, validation_targets, _ = build_rows(
        series_by_key,
        validation_start,
        test_start,
    )
    candidate_model = tree_pipeline()
    candidate_model.fit(candidate_training_features, candidate_training_targets)
    tree_validation = metrics(validation_targets, candidate_model.predict(validation_features))
    baseline_validation_actual, baseline_validation_predictions = selected_baseline_predictions(
        series_by_key,
        selected_baselines,
        validation_start,
        test_start,
    )
    baseline_validation = metrics(baseline_validation_actual, baseline_validation_predictions)
    promotion_gate = promotion_decision(
        baseline_validation,
        tree_validation,
        minimum_relative_wape_improvement,
    )

    training_features, training_targets, _ = build_rows(series_by_key, 52, test_start)
    test_features, test_targets, _ = build_rows(series_by_key, test_start, weeks)
    model = tree_pipeline()
    model.fit(training_features, training_targets)
    tree_predictions = model.predict(test_features)

    baseline_test_actual, baseline_predictions = selected_baseline_predictions(
        series_by_key,
        selected_baselines,
        test_start,
        weeks,
    )

    if baseline_test_actual != test_targets:
        raise ValueError("Tree and baseline test observations are not aligned.")
    baseline_test = metrics(baseline_test_actual, baseline_predictions)
    tree_test = metrics(test_targets, tree_predictions)
    test_relative_improvement = 100 * (baseline_test.wape - tree_test.wape) / max(baseline_test.wape, 1e-9)
    first_series = next(iter(series_by_key.values()))

    return {
        "experiment": "random_forest_vs_time_series_baseline_v1",
        "source_file": path.name,
        "dataset": {
            "rows": sum(len(series) for series in series_by_key.values()),
            "series": len(series_by_key),
            "weeks_per_series": weeks,
            "history_start": first_series[0].week_start.isoformat(),
            "history_through": (first_series[-1].week_start + timedelta(days=6)).isoformat(),
        },
        "split": {
            "train": {"start_index": 0, "end_index": validation_start - 1},
            "validation": {"start_index": validation_start, "end_index": test_start - 1},
            "test": {"start_index": test_start, "end_index": weeks - 1},
            "holdout_weeks": HOLDOUT_WEEKS,
            "test_used_for_selection": False,
        },
        "features": list(FEATURE_NAMES),
        "random_forest": {
            "parameters": {
                "n_estimators": 300,
                "min_samples_leaf": 3,
                "max_features": 0.8,
                "random_state": 42,
            },
            "validation_metrics": tree_validation.as_dict(),
            "test_metrics": tree_test.as_dict(),
        },
        "baseline": {
            "validation_candidate_metrics": validation_metrics,
            "validation_selected_metrics": baseline_validation.as_dict(),
            "selected_model_counts": dict(
                sorted(
                    (model_name, list(selected_baselines.values()).count(model_name))
                    for model_name in BASELINE_MODELS
                )
            ),
            "test_metrics": baseline_test.as_dict(),
        },
        "promotion_gate": {**promotion_gate, "evaluation_split": "validation"},
        "test_confirmation": {
            "tree_wape_better": tree_test.wape < baseline_test.wape,
            "tree_mae_not_worse": tree_test.mae <= baseline_test.mae,
            "relative_wape_improvement_percent": round(test_relative_improvement, 4),
            "used_for_model_or_hyperparameter_selection": False,
        },
    }
