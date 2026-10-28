from __future__ import annotations

import argparse
import json
from pathlib import Path

from app.evaluation import evaluate_dataset


def main() -> None:
    parser = argparse.ArgumentParser(description="Evaluate Pandansari forecasting candidates.")
    parser.add_argument("--dataset", type=Path, required=True)
    parser.add_argument("--output", type=Path)
    parser.add_argument("--minimum-wape-improvement", type=float, default=2.0)
    arguments = parser.parse_args()

    report = evaluate_dataset(arguments.dataset.resolve(), arguments.minimum_wape_improvement)
    rendered = json.dumps(report, indent=2, ensure_ascii=False) + "\n"

    if arguments.output:
        arguments.output.parent.mkdir(parents=True, exist_ok=True)
        arguments.output.write_text(rendered, encoding="utf-8")
    print(rendered, end="")


if __name__ == "__main__":
    main()
