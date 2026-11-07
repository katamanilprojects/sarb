"""Explicit non-secret pipeline settings. PHP owns application DB credentials."""
import json
import os
import sys
from pathlib import Path

PIPELINE_DIR = Path(__file__).resolve().parents[1]
PROJECT_DIR = PIPELINE_DIR.parent


def outside_project(path):
    return not path.is_relative_to(PROJECT_DIR)


def load_config():
    if sys.version_info < (3, 10):
        raise ValueError("Python 3.10+ is required.")
    value = os.environ.get("AI_PIPELINE_CONFIG", "")
    path = Path(value).resolve() if value else None
    if path is None or not Path(value).is_absolute() or not path.is_file():
        raise ValueError("Set AI_PIPELINE_CONFIG to an absolute private pipeline config JSON file.")
    if not outside_project(path):
        raise ValueError("Keep pipeline configuration outside the web project.")
    cfg = json.loads(path.read_text(encoding="utf-8-sig"))
    if cfg.get("environment") not in ("staging", "production"):
        raise ValueError("environment must be staging or production.")
    runtime = Path(cfg.get("runtime_dir", ""))
    if not runtime.is_absolute() or not outside_project(runtime.resolve()):
        raise ValueError("runtime_dir must be an absolute directory outside the web project.")
    for key in (("php_executable", "agent_executable") if cfg["environment"] == "staging" else ("php_executable",)):
        exe = Path(cfg.get(key, ""))
        if not exe.is_absolute() or not exe.is_file():
            raise ValueError(f"{key} must name an existing absolute executable path.")
        if any(c in str(exe) for c in '\r\n%&|<>^!'):
            raise ValueError(f"Unsupported shell characters in {key}.")
    db = cfg.get("test_database", "")
    if not isinstance(db, str) or not db.startswith("cea_standardisation_test_") or not all(c.isalnum() or c == "_" for c in db):
        raise ValueError("Use an isolated cea_standardisation_test_ database.")
    for key, default in (("task_timeout_seconds", 3000), ("verification_timeout_seconds", 600), ("max_attempts", 3), ("batch_timeout_seconds", 3600)):
        number = cfg.get(key, default)
        if type(number) is not int or number < 1:
            raise ValueError(f"{key} must be a positive integer.")
        cfg[key] = number
    if type(cfg.get("process_ai_jobs", False)) is not bool:
        raise ValueError("process_ai_jobs must be true or false.")
    if type(cfg.get("allow_production_batch", False)) is not bool:
        raise ValueError("allow_production_batch must be true or false.")
    from urllib.parse import urlparse
    endpoint = cfg.get("ollama_endpoint", "http://127.0.0.1:11434")
    parts = urlparse(endpoint)
    if parts.scheme != "http" or parts.hostname not in ("127.0.0.1", "localhost", "::1") or parts.username or parts.password or parts.query or parts.fragment or parts.path not in ("", "/"):
        raise ValueError("Use a loopback HTTP Ollama base URL.")
    cfg["ollama_endpoint"] = endpoint.rstrip("/")
    cfg["ollama_model"] = cfg.get("ollama_model", "gemma2:2b")
    if not isinstance(cfg["ollama_model"], str) or not cfg["ollama_model"]:
        raise ValueError("ollama_model is required.")
    cfg["runtime_dir"] = str(runtime.resolve())
    return cfg


def database_arg(cfg):
    return ["--database=" + cfg["test_database"]] if cfg["environment"] == "staging" else []
