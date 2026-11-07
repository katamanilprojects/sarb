"""One bounded Antigravity run, with dependency checks and runner-owned state."""
import argparse
from datetime import datetime, timezone
import json
import os
from pathlib import Path
import sys
import uuid
from config import PIPELINE_DIR, PROJECT_DIR, load_config
from runtime import exclusive_lock, run_command, write_json

STATUSES = {"PENDING", "IN_PROGRESS", "TESTED_DONE", "FAILED", "BLOCKED"}
PROMPT = "Read ai_pipeline/cron_runner_prompt.md and perform only the task specified by AI_PIPELINE_ASSIGNMENT. Do not edit pipeline runtime state."


def now():
    return datetime.now(timezone.utc).isoformat()


def validate_roadmap(state):
    tasks = state.get("tasks", [])
    ids = [t["id"] for t in tasks]
    if not ids or len(ids) != len(set(ids)):
        raise ValueError("Roadmap needs unique task IDs.")
    seen = set()
    for task in tasks:
        if task.get("status") not in STATUSES or not set(task.get("depends_on", [])).issubset(seen):
            raise ValueError("Invalid status, dependency order or dependency cycle.")
        if not task.get("checks") or not task.get("required_outputs") or not task.get("acceptance_criteria"):
            raise ValueError("Each task needs verification commands, required outputs and acceptance criteria.")
        for path in task["required_outputs"]:
            full = (PROJECT_DIR / path).resolve()
            if not full.is_relative_to(PIPELINE_DIR):
                raise ValueError("Task outputs must stay inside ai_pipeline.")
        for command in task["checks"]:
            if not isinstance(command, list) or not command or not all(isinstance(arg, str) and arg for arg in command):
                raise ValueError("Checks must be nonempty argument arrays, never shell strings.")
            if command[0] not in ("{python}", "{php}"):
                raise ValueError("Only configured Python/PHP interpreters may run task checks.")
        seen.add(task["id"])
    return state


def choose_task(state):
    if any(t["status"] in ("FAILED", "BLOCKED", "IN_PROGRESS") for t in state["tasks"]):
        return None
    done = {t["id"] for t in state["tasks"] if t["status"] == "TESTED_DONE"}
    return next((t for t in state["tasks"] if t["status"] == "PENDING" and set(t["depends_on"]).issubset(done)), None)


def save_state(path, state):
    state["last_updated"] = now()
    state["status_summary"] = {s.lower(): sum(t["status"] == s for t in state["tasks"]) for s in sorted(STATUSES)}
    state["status_summary"]["total"] = len(state["tasks"])
    write_json(path, state)


def expand_check(command, cfg):
    replacements = {"{python}": sys.executable, "{php}": cfg["php_executable"],
                    "{test_database}": cfg["test_database"]}
    result = []
    for arg in command:
        for key, value in replacements.items():
            arg = arg.replace(key, value)
        if "{" in arg or "}" in arg:
            raise ValueError("Unknown command placeholder.")
        result.append(arg)
    return result


def validate_receipt(receipt, task, run_id):
    if receipt.get("task_id") != task["id"] or receipt.get("run_id") != run_id:
        raise ValueError("Missing receipt or wrong task/run identity.")
    if receipt.get("status") not in ("COMPLETE", "BLOCKED"):
        raise ValueError("Receipt status must be COMPLETE or BLOCKED.")
    if not isinstance(receipt.get("summary"), str) or not receipt["summary"].strip():
        raise ValueError("Receipt requires an implementation summary or blocking reason.")
    if receipt["status"] == "COMPLETE":
        evidence = receipt.get("acceptance_evidence")
        if not isinstance(evidence, list) or len(evidence) != len(task["acceptance_criteria"]) or not all(isinstance(x, str) and x.strip() for x in evidence):
            raise ValueError("Receipt must explain evidence for every acceptance criterion.")


def run_task(cfg, runtime, state, state_path):
    # A crashed parent may leave a child running. Never reassign its task automatically.
    stale = [t for t in state["tasks"] if t["status"] == "IN_PROGRESS"]
    if stale:
        for task in stale:
            task.update(status="FAILED", last_error="Interrupted run. Stop any surviving agent/child processes and inspect changes before retry --confirm-stopped.", finished_at=now())
        save_state(state_path, state)
        raise RuntimeError("Interrupted work requires explicit recovery; no task was dispatched.")
    task = choose_task(state)
    if task is None:
        if all(t["status"] == "TESTED_DONE" for t in state["tasks"]):
            print("All roadmap tasks tested in staging. Production deployment is separate.")
            return 0
        raise RuntimeError("Roadmap is blocked by a failed/blocked task or unmet dependencies.")
    if task.get("attempts", 0) >= cfg["max_attempts"]:
        raise RuntimeError("Maximum attempts reached; revise the task after investigating.")
    run_id = uuid.uuid4().hex
    run_dir = runtime / "runs" / (task["id"] + "-" + run_id)
    run_dir.mkdir(parents=True)
    assignment = run_dir / "assignment.json"
    receipt_path = run_dir / "result.json"
    write_json(assignment, {"run_id": run_id, "task": task, "result_path": str(receipt_path),
                            "test_database": cfg["test_database"], "php_executable": cfg["php_executable"],
                            "python_executable": sys.executable, "workspace": str(PROJECT_DIR)})
    task.update(status="IN_PROGRESS", attempts=task.get("attempts", 0) + 1, run_id=run_id,
                started_at=now(), finished_at=None, completed_at=None, last_error=None, run_log=str(run_dir))
    save_state(state_path, state)
    env = os.environ.copy()
    env["AI_PIPELINE_ASSIGNMENT"] = str(assignment)
    env["AI_PIPELINE_ALLOW_WRITES"] = "0"
    try:
        run_command([cfg["agent_executable"], "-p", PROMPT], PROJECT_DIR,
                    run_dir / "agent.log", cfg["task_timeout_seconds"], env)
        receipt = json.loads(receipt_path.read_text(encoding="utf-8-sig"))
        validate_receipt(receipt, task, run_id)
        if receipt["status"] == "BLOCKED":
            task.update(status="BLOCKED", last_error=receipt["summary"][:2000])
            return 1
        missing = [p for p in task["required_outputs"] if not (PROJECT_DIR / p).is_file()]
        if missing:
            raise ValueError("Required implementation files missing: " + ", ".join(missing))
        for index, command in enumerate(task["checks"]):
            run_command(expand_check(command, cfg), PROJECT_DIR, run_dir / f"check-{index + 1}.log",
                        cfg["verification_timeout_seconds"], env)
        task.update(status="TESTED_DONE", completed_at=now(), evidence=receipt["acceptance_evidence"], summary=receipt["summary"])
        return 0
    except BaseException as exc:
        task.update(status="FAILED", last_error=f"{type(exc).__name__}: {exc}"[:2000])
        raise
    finally:
        task["finished_at"] = now()
        save_state(state_path, state)
        with (runtime / "execution_log.jsonl").open("a", encoding="utf-8") as stream:
            stream.write(json.dumps({"timestamp": now(), "task_id": task["id"], "run_id": run_id,
                                     "status": task["status"], "run_log": str(run_dir)}) + "\n")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=["run", "status", "retry"])
    parser.add_argument("--task")
    parser.add_argument("--confirm-stopped", action="store_true")
    args = parser.parse_args()
    cfg = load_config()
    if cfg["environment"] != "staging":
        raise ValueError("Autonomous development requires a staging checkout and configuration.")
    runtime = Path(cfg["runtime_dir"])
    with exclusive_lock(runtime / "development.lock"):
        path = runtime / "ai_tasks.state.json"
        source = path if path.exists() else PIPELINE_DIR / "ai_tasks.json"
        state = validate_roadmap(json.loads(source.read_text(encoding="utf-8-sig")))
        if args.action == "status":
            print(json.dumps([{k: t.get(k) for k in ("id", "status", "attempts", "last_error")} for t in state["tasks"]], indent=2))
            return 0
        if args.action == "retry":
            if not args.task or not args.confirm_stopped:
                raise ValueError("Retry requires --task TASK-ID --confirm-stopped after stopping surviving processes and inspecting changes.")
            task = next((t for t in state["tasks"] if t["id"] == args.task), None)
            if not task or task["status"] not in ("FAILED", "BLOCKED"):
                raise ValueError("Only a failed or blocked task may be retried.")
            if task.get("attempts", 0) >= cfg["max_attempts"]:
                raise ValueError("Maximum attempts reached. Investigate and revise the roadmap explicitly.")
            task.update(status="PENDING", last_error=None)
            save_state(path, state)
            return 0
        return run_task(cfg, runtime, state, path)


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (Exception, KeyboardInterrupt) as exc:
        print(f"Runner stopped: {type(exc).__name__}: {exc}", file=sys.stderr)
        sys.exit(1)
