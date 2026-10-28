from __future__ import annotations

import csv
from datetime import date, timedelta
from pathlib import Path

from app.evaluation import ModelMetrics, evaluate_dataset, promotion_decision
from app.main import health


def write_dataset(path: Path) -> None:
    start = date(2022, 1, 3)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=["week_start", "outlet", "sku", "units_sold"])
        writer.writeheader()
        for sku_index, sku in enumerate(("A", "B"), start=1):
            for week in range(208):
                writer.writerow(
                    {
                        "week_start": (start + timedelta(weeks=week)).isoformat(),
                        "outlet": "Outlet 1",
                        "sku": sku,
                        "units_sold": 20 + sku_index + (week % 13),
                    }
                )


def test_evaluation_preserves_the_final_test_set_for_reporting_only(tmp_path: Path) -> None:
    dataset = tmp_path / "sales.csv"
    write_dataset(dataset)

    report = evaluate_dataset(dataset)

    assert report["dataset"]["rows"] == 416
    assert report["split"]["validation"] == {"start_index": 104, "end_index": 155}
    assert report["split"]["test"] == {"start_index": 156, "end_index": 207}
    assert report["split"]["test_used_for_selection"] is False
    assert report["random_forest"]["parameters"]["random_state"] == 42
    assert report["baseline"]["test_metrics"]["observations"] == 104
    assert report["promotion_gate"]["evaluation_split"] == "validation"
    assert report["test_confirmation"]["used_for_model_or_hyperparameter_selection"] is False


def test_promotion_gate_requires_both_wape_improvement_and_non_worse_mae() -> None:
    baseline = ModelMetrics(mae=10, rmse=12, wape=20, observations=52)
    passing_tree = ModelMetrics(mae=9, rmse=11, wape=18, observations=52)
    worse_mae_tree = ModelMetrics(mae=11, rmse=11, wape=18, observations=52)

    assert promotion_decision(baseline, passing_tree, 2)["passed"] is True
    assert promotion_decision(baseline, worse_mae_tree, 2)["passed"] is False


def test_health_response_identifies_the_service() -> None:
    assert health() == {"status": "ok", "service": "pandansari-ai"}
