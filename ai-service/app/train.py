from __future__ import annotations

import argparse
import json
from pathlib import Path

from app.artifact import train_promoted_artifact


def main() -> None:
    parser = argparse.ArgumentParser(description="Train a promoted Pandansari forecasting artifact.")
    parser.add_argument("--dataset", type=Path, required=True)
    parser.add_argument("--report", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    arguments = parser.parse_args()
    metadata = train_promoted_artifact(
        arguments.dataset.resolve(),
        arguments.report.resolve(),
        arguments.output.resolve(),
    )
    print(json.dumps(metadata, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
