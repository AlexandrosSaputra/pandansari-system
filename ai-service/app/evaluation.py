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
from sklearn.ensemble import ExtraTreesRegressor, GradientBoostingRegressor, RandomForestRegressor
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

HOLDOUT_WEEKS = 52
MINIMUM_HISTORY_WEEKS = 208
BASELINE_MODELS = ("lag_1", "moving_average_4", "seasonal_52", "seasonal_average_3")
BASELINE_MINIMUM_HISTORY = {
    "lag_1": 1,
    "moving_average_4": 4,
    "seasonal_52": 52,
    "seasonal_average_3": 156,
}
TREE_CANDIDATES = ("random_forest", "extra_trees", "gradient_boosting")
TREE_PARAMETERS: dict[str, dict[str, Any]] = {
    "random_forest": {
        "n_estimators": 300,
        "min_samples_leaf": 3,
        "max_features": 0.8,
        "random_state": 42,
    },
    "extra_trees": {
        "n_estimators": 300,
        "min_samples_leaf": 3,
        "max_features": 0.8,
        "random_state": 42,
    },
    "gradient_boosting": {
        "n_estimators": 200,
        "learning_rate": 0.03,
        "max_depth": 3,
        "min_samples_leaf": 3,
        "loss": "huber",
        "random_state": 42,
    },
}
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


def tree_pipeline(candidate: str) -> Pipeline:
    preprocessing = ColumnTransformer(
        [("categorical", OneHotEncoder(handle_unknown="ignore", sparse_output=False), [0, 1])],
        remainder="passthrough",
    )
    parameters = TREE_PARAMETERS.get(candidate)
    if parameters is None:
        raise ValueError(f"Unsupported tree candidate: {candidate}")
    if candidate == "random_forest":
        model = RandomForestRegressor(**parameters, n_jobs=1)
    elif candidate == "extra_trees":
        model = ExtraTreesRegressor(**parameters, n_jobs=1)
    else:
        model = GradientBoostingRegressor(**parameters)
    return Pipeline([("features", preprocessing), ("model", model)])


def baseline_prediction(values: list[float], index: int, model: str) -> float:
    if model == "lag_1":
        return values[index - 1]
    if model == "moving_average_4":
        return sum(values[index - 4 : index]) / 4
    if model == "seasonal_52":
        return values[index - 52]
    if model == "seasonal_average_3":
        return (values[index - 52] + values[index - 104] + values[index - 156]) / 3
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
) -> tuple[dict[tuple[str, str], str], dict[str, dict[str, float | int]], tuple[str, ...]]:
    selected: dict[tuple[str, str], str] = {}
    aggregate_actual: dict[str, list[float]] = defaultdict(list)
    aggregate_predictions: dict[str, list[float]] = defaultdict(list)
    eligible_models = tuple(
        model for model in BASELINE_MODELS
        if validation_start >= BASELINE_MINIMUM_HISTORY[model]
    )

    for key, series in series_by_key.items():
        values = [sale.units_sold for sale in series]
        scored: list[tuple[float, float, str]] = []
        actual = values[validation_start:test_start]
        for model in eligible_models:
            predictions = [baseline_prediction(values, index, model) for index in range(validation_start, test_start)]
            score = metrics(actual, predictions)
            scored.append((score.mae, score.rmse, model))
            aggregate_actual[model].extend(actual)
            aggregate_predictions[model].extend(predictions)
        selected[key] = min(scored)[2]

    validation_metrics = {
        model: metrics(aggregate_actual[model], aggregate_predictions[model]).as_dict()
        for model in eligible_models
    }
    return selected, validation_metrics, eligible_models


def baseline_candidate_metrics(
    series_by_key: dict[tuple[str, str], list[WeeklySale]],
    start: int,
    end: int,
    models: Iterable[str],
) -> dict[str, ModelMetrics]:
    candidate_metrics: dict[str, ModelMetrics] = {}

    for model in models:
        minimum_history = BASELINE_MINIMUM_HISTORY.get(model)
        if minimum_history is None:
            raise ValueError(f"Unsupported baseline model: {model}")
        if start < minimum_history:
            raise ValueError(f"Baseline model {model} requires {minimum_history} prior weeks.")
        actual: list[float] = []
        predicted: list[float] = []
        for series in series_by_key.values():
            values = [sale.units_sold for sale in series]
            actual.extend(values[start:end])
            predicted.extend(baseline_prediction(values, index, model) for index in range(start, end))
        candidate_metrics[model] = metrics(actual, predicted)

    return candidate_metrics


def promotion_decision(
    baseline: ModelMetrics,
    tree: ModelMetrics,
    minimum_relative_wape_improvement: float,
    candidate: str = "random_forest",
) -> dict[str, Any]:
    relative_improvement = 100 * (baseline.wape - tree.wape) / max(baseline.wape, 1e-9)
    passed = relative_improvement >= minimum_relative_wape_improvement and tree.mae <= baseline.mae
    return {
        "passed": passed,
        "candidate": candidate,
        "relative_wape_improvement_percent": round(relative_improvement, 4),
        "minimum_required_improvement_percent": minimum_relative_wape_improvement,
        "reason": (
            f"{candidate} passed the WAPE improvement gate without worsening MAE."
            if passed
            else f"{candidate} did not pass both the WAPE improvement and MAE gates."
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

    selected_baselines, validation_metrics, validation_baseline_models = select_baselines(
        series_by_key,
        validation_start,
        test_start,
    )

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
    candidate_validation: dict[str, ModelMetrics] = {}
    for candidate in TREE_CANDIDATES:
        candidate_model = tree_pipeline(candidate)
        candidate_model.fit(candidate_training_features, candidate_training_targets)
        candidate_validation[candidate] = metrics(
            validation_targets,
            candidate_model.predict(validation_features),
        )
    selected_candidate = min(
        TREE_CANDIDATES,
        key=lambda name: (
            candidate_validation[name].mae,
            candidate_validation[name].rmse,
            candidate_validation[name].wape,
            name,
        ),
    )
    tree_validation = candidate_validation[selected_candidate]
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
        selected_candidate,
    )
    first_series = next(iter(series_by_key.values()))
    report: dict[str, Any] = {
        "experiment": "tree_candidates_vs_four_time_series_baselines_v3",
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
        "tree_candidates": {
            "selection_metric": "lowest validation MAE, then RMSE, then WAPE",
            "parameters": TREE_PARAMETERS,
            "validation_metrics": {
                candidate: candidate_validation[candidate].as_dict()
                for candidate in TREE_CANDIDATES
            },
            "selected": selected_candidate,
        },
        "baseline": {
            "models": list(BASELINE_MODELS),
            "validation_eligible_models": list(validation_baseline_models),
            "validation_ineligible_models": {
                model: f"requires {BASELINE_MINIMUM_HISTORY[model]} prior weeks"
                for model in BASELINE_MODELS
                if model not in validation_baseline_models
            },
            "validation_candidate_metrics": validation_metrics,
            "validation_selected_metrics": baseline_validation.as_dict(),
            "selected_model_counts": dict(
                sorted(
                    (model_name, list(selected_baselines.values()).count(model_name))
                    for model_name in BASELINE_MODELS
                )
            ),
        },
        "promotion_gate": {**promotion_gate, "evaluation_split": "validation"},
        "test_confirmation": {
            "evaluated": False,
            "reason": "Test set is withheld because the selected candidate did not pass validation gate.",
            "used_for_model_or_hyperparameter_selection": False,
        },
        "deployment_decision": {
            "passed": False,
            "reason": "Validation gate did not pass; test confirmation was not evaluated.",
        },
    }

    if not promotion_gate["passed"]:
        return report

    training_features, training_targets, _ = build_rows(series_by_key, 52, test_start)
    test_features, test_targets, _ = build_rows(series_by_key, test_start, weeks)
    model = tree_pipeline(selected_candidate)
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
    test_baseline_candidates = baseline_candidate_metrics(
        series_by_key,
        test_start,
        weeks,
        BASELINE_MODELS,
    )
    test_reference_model = min(
        BASELINE_MODELS,
        key=lambda name: (
            test_baseline_candidates[name].wape,
            test_baseline_candidates[name].mae,
            test_baseline_candidates[name].rmse,
            name,
        ),
    )
    test_reference = test_baseline_candidates[test_reference_model]
    test_decision = promotion_decision(
        test_reference,
        tree_test,
        minimum_relative_wape_improvement,
        selected_candidate,
    )
    report["tree_candidates"]["test_metrics"] = tree_test.as_dict()
    report["baseline"]["test_metrics"] = baseline_test.as_dict()
    report["baseline"]["test_candidate_metrics"] = {
        model_name: test_baseline_candidates[model_name].as_dict()
        for model_name in BASELINE_MODELS
    }
    report["test_confirmation"] = {
        **test_decision,
        "evaluated": True,
        "baseline_reference_model": test_reference_model,
        "baseline_reference_metrics": test_reference.as_dict(),
        "tree_wape_better": tree_test.wape < test_reference.wape,
        "tree_mae_not_worse": tree_test.mae <= test_reference.mae,
        "evaluation_split": "test",
        "used_for_model_or_hyperparameter_selection": False,
    }
    report["deployment_decision"] = {
        "passed": test_decision["passed"],
        "validation_gate_passed": True,
        "test_confirmation_passed": test_decision["passed"],
        "reason": (
            "Selected tree candidate passed validation gate and locked test confirmation."
            if test_decision["passed"]
            else "Selected tree candidate passed validation but failed locked test confirmation."
        ),
    }
    return report
