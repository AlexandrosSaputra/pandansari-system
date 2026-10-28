from __future__ import annotations

import gzip
import hashlib
import json
import pickle
from pathlib import Path
from typing import Any

from app.evaluation import FEATURE_NAMES, build_rows, load_sales, tree_pipeline

ARTIFACT_SCHEMA_VERSION = 2


def train_promoted_artifact(
    dataset_path: Path,
    report_path: Path,
    artifact_path: Path,
) -> dict[str, Any]:
    report = json.loads(report_path.read_text(encoding="utf-8"))
    promotion_gate = report.get("promotion_gate", {})
    tree_candidates = report.get("tree_candidates", {})
    candidate = tree_candidates.get("selected")

    if promotion_gate.get("passed") is not True or promotion_gate.get("candidate") != candidate:
        raise ValueError("Evaluation report does not contain a promoted tree candidate.")
    if not isinstance(candidate, str):
        raise ValueError("Evaluation report does not identify the selected tree candidate.")
    validation_metrics = tree_candidates.get("validation_metrics", {}).get(candidate)
    test_metrics = tree_candidates.get("test_metrics")
    if not isinstance(validation_metrics, dict) or not isinstance(test_metrics, dict):
        raise ValueError("Evaluation report does not contain validation and test metrics.")

    series_by_key = load_sales(dataset_path)
    lengths = {len(series) for series in series_by_key.values()}
    if len(lengths) != 1:
        raise ValueError("All SKU series must cover the same number of weeks.")
    weeks = lengths.pop()
    features, targets, _ = build_rows(series_by_key, 52, weeks)
    model = tree_pipeline(candidate)
    model.fit(features, targets)
    first_series = next(iter(series_by_key.values()))
    metadata = {
        "schema_version": ARTIFACT_SCHEMA_VERSION,
        "model_version": f"{candidate}_v1",
        "candidate": candidate,
        "dataset_sha256": hashlib.sha256(dataset_path.read_bytes()).hexdigest(),
        "training_rows": len(targets),
        "series": len(series_by_key),
        "weeks_per_series": weeks,
        "trained_through": (first_series[-1].week_start).isoformat(),
        "features": list(FEATURE_NAMES),
        "evaluation": {
            "validation": validation_metrics,
            "test": test_metrics,
        },
    }
    payload = {"metadata": metadata, "model": model}
    artifact_path.parent.mkdir(parents=True, exist_ok=True)
    temporary_path = artifact_path.with_suffix(f"{artifact_path.suffix}.tmp")
    opener = gzip.open if artifact_path.suffix == ".gz" else Path.open
    with opener(temporary_path, "wb") as handle:
        pickle.dump(payload, handle, protocol=pickle.HIGHEST_PROTOCOL)
    temporary_path.replace(artifact_path)

    return metadata


def load_artifact(artifact_path: Path) -> dict[str, Any]:
    opener = gzip.open if artifact_path.suffix == ".gz" else Path.open
    try:
        with opener(artifact_path, "rb") as handle:
            payload = pickle.load(handle)  # noqa: S301 - generated and deployed by this project.
    except (AttributeError, EOFError, ImportError, IndexError, pickle.PickleError) as error:
        raise ValueError("Forecast model artifact cannot be decoded.") from error

    if not isinstance(payload, dict) or not isinstance(payload.get("metadata"), dict):
        raise ValueError("Forecast model artifact has an invalid structure.")
    if payload["metadata"].get("schema_version") != ARTIFACT_SCHEMA_VERSION:
        raise ValueError("Forecast model artifact schema is not supported.")
    if not hasattr(payload.get("model"), "predict"):
        raise ValueError("Forecast model artifact does not contain a usable estimator.")

    return payload
