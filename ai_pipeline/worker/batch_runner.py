"""Bounded nightly operational batch. Development automation is a separate runner."""
from datetime import datetime, timezone
import os
from pathlib import Path
import sys
from config import PIPELINE_DIR, PROJECT_DIR, load_config, database_arg
from runtime import exclusive_lock, run_command


def main():
    cfg = load_config()
    if cfg["environment"] == "production" and not cfg.get("allow_production_batch", False):
        raise ValueError("Production batch is disabled. Validate and deploy first, then enable it explicitly.")
    runtime = Path(cfg["runtime_dir"])
    with exclusive_lock(runtime / "development.lock"):
        log = runtime / "logs" / ("nightly-" + datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%S%fZ") + ".log")
        log.parent.mkdir(parents=True, exist_ok=True)
        env = os.environ.copy()
        env["AI_PIPELINE_ALLOW_WRITES"] = "1"
        run_command([cfg["php_executable"], str(PIPELINE_DIR / "tests/verify_pipeline.php"), "--check-schema", *database_arg(cfg)],
                    PROJECT_DIR, log, cfg["verification_timeout_seconds"], env)
        run_command([cfg["php_executable"], str(PIPELINE_DIR / "worker/attendance_batch.php"), "--write", *database_arg(cfg)],
                    PROJECT_DIR, log, cfg["batch_timeout_seconds"], env)
        if cfg.get("process_ai_jobs", False):
            run_command([cfg["php_executable"], str(PROJECT_DIR / "tools/ai_worker.php"), "--limit=10", *database_arg(cfg)],
                        PROJECT_DIR, log, cfg["batch_timeout_seconds"], env)
        with log.open("a", encoding="utf-8") as stream:
            stream.write("Batch commands finished. Inspect AI job statuses for individual failures; worker exit success alone does not certify every draft.\n")
        print(f"Batch completed. Log: {log}")
        return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as exc:
        print(f"Batch failed: {exc}", file=sys.stderr)
        sys.exit(1)
