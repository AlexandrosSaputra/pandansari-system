from __future__ import annotations

import csv
import json
from datetime import date, timedelta
from pathlib import Path

from app.artifact import load_artifact, train_promoted_artifact
from app.evaluation import ModelMetrics, evaluate_dataset, promotion_decision
from app.inference import forecast_weekly
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


def test_evaluation_selects_candidates_on_validation_and_withholds_failed_test(tmp_path: Path) -> None:
    dataset = tmp_path / "sales.csv"
    write_dataset(dataset)

    report = evaluate_dataset(dataset)

    assert report["dataset"]["rows"] == 416
    assert report["split"]["validation"] == {"start_index": 104, "end_index": 155}
    assert report["split"]["test"] == {"start_index": 156, "end_index": 207}
    assert report["split"]["test_used_for_selection"] is False
    assert set(report["tree_candidates"]["validation_metrics"]) == {
        "random_forest",
        "extra_trees",
        "gradient_boosting",
    }
    assert report["tree_candidates"]["selected"] in report["tree_candidates"]["validation_metrics"]
    assert report["promotion_gate"]["passed"] is False
    assert report["promotion_gate"]["evaluation_split"] == "validation"
    assert report["test_confirmation"]["evaluated"] is False
    assert "test_metrics" not in report["tree_candidates"]
    assert "test_metrics" not in report["baseline"]
    assert report["test_confirmation"]["used_for_model_or_hyperparameter_selection"] is False


def test_promotion_gate_requires_both_wape_improvement_and_non_worse_mae() -> None:
    baseline = ModelMetrics(mae=10, rmse=12, wape=20, observations=52)
    passing_tree = ModelMetrics(mae=9, rmse=11, wape=18, observations=52)
    worse_mae_tree = ModelMetrics(mae=11, rmse=11, wape=18, observations=52)

    assert promotion_decision(baseline, passing_tree, 2, "extra_trees")["passed"] is True
    assert promotion_decision(baseline, worse_mae_tree, 2, "extra_trees")["passed"] is False


def test_health_response_identifies_the_service() -> None:
    assert health() == {"status": "ok", "service": "pandansari-ai"}


def test_promoted_artifact_supports_recursive_weekly_inference(tmp_path: Path) -> None:
    dataset = tmp_path / "sales.csv"
    report = tmp_path / "report.json"
    artifact_path = tmp_path / "extra_trees_v1.pkl.gz"
    write_dataset(dataset)
    report.write_text(json.dumps({
        "promotion_gate": {"passed": True, "candidate": "extra_trees"},
        "tree_candidates": {
            "selected": "extra_trees",
            "validation_metrics": {
                "extra_trees": {"mae": 2.0, "rmse": 3.0, "wape": 10.0, "observations": 104},
            },
            "test_metrics": {"mae": 1.5, "rmse": 2.5, "wape": 8.0, "observations": 104},
        },
    }), encoding="utf-8")

    metadata = train_promoted_artifact(dataset, report, artifact_path)
    artifact = load_artifact(artifact_path)
    weeks = [date(2022, 1, 3) + timedelta(weeks=week) for week in range(208)]
    values = [21 + (week % 13) for week in range(208)]
    predictions = forecast_weekly(artifact, "Outlet 1", "A", weeks, values, 2)

    assert metadata["model_version"] == "extra_trees_v1"
    assert metadata["training_rows"] == 312
    assert metadata["evaluation"]["test"]["rmse"] == 2.5
    assert predictions[0]["week_start"] == "2025-12-29"
    assert predictions[1]["week_start"] == "2026-01-05"
    assert all(prediction["units"] >= 0 for prediction in predictions)


def test_artifact_training_rejects_a_candidate_that_did_not_pass_gate(tmp_path: Path) -> None:
    dataset = tmp_path / "sales.csv"
    report = tmp_path / "report.json"
    write_dataset(dataset)
    report.write_text(json.dumps({
        "promotion_gate": {"passed": False, "candidate": "extra_trees"},
        "tree_candidates": {"selected": "extra_trees"},
    }), encoding="utf-8")

    try:
        train_promoted_artifact(dataset, report, tmp_path / "model.pkl")
    except ValueError as error:
        assert "promoted tree candidate" in str(error)
    else:
        raise AssertionError("Training should reject an unpromoted candidate.")
