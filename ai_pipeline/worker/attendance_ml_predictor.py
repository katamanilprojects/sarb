"""Compatibility entry point: use the application's policy-aware PHP attendance service.

This is deterministic provisional analytics, not a trained prediction model.
"""
import argparse
import os
import subprocess
import sys
from config import PIPELINE_DIR, PROJECT_DIR, load_config, database_arg


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--dry-run", action="store_true")
    mode.add_argument("--write", action="store_true")
    parser.add_argument("--class", dest="class_id", type=int)
    args = parser.parse_args()
    cfg = load_config()
    if args.write and cfg["environment"] == "production" and not cfg.get("allow_production_batch", False):
        raise ValueError("Production batch writes have not been enabled in private configuration.")
    command = [cfg["php_executable"], str(PIPELINE_DIR / "worker/attendance_batch.php"),
               "--write" if args.write else "--dry-run", *database_arg(cfg)]
    if args.class_id is not None:
        if args.class_id < 1:
            raise ValueError("class must be positive.")
        command.append("--class=" + str(args.class_id))
    env = os.environ.copy()
    env["AI_PIPELINE_ALLOW_WRITES"] = "1" if args.write else "0"
    return subprocess.run(command, cwd=PROJECT_DIR, env=env, timeout=cfg["batch_timeout_seconds"]).returncode


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (ValueError, OSError, subprocess.SubprocessError) as exc:
        print(f"Attendance runner failed: {exc}", file=sys.stderr)
        sys.exit(1)
