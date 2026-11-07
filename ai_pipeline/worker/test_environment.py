"""Read-only checks. Any missing prerequisite returns a nonzero exit status."""
import argparse
import json
import subprocess
import sys
from urllib.request import build_opener, ProxyHandler
from config import PIPELINE_DIR, PROJECT_DIR, load_config, database_arg


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--skip-ollama", action="store_true", help="Only check Python and DB; not LLM readiness.")
    args = parser.parse_args()
    if sys.version_info < (3, 10):
        raise ValueError("Python 3.10+ is required.")
    cfg = load_config()
    subprocess.run([cfg["php_executable"], str(PIPELINE_DIR / "tests/verify_pipeline.php"),
                    "--check-core-schema", *database_arg(cfg)], cwd=PROJECT_DIR, check=True, timeout=60)
    if not args.skip_ollama:
        opener = build_opener(ProxyHandler({}))
        with opener.open(cfg["ollama_endpoint"] + "/api/tags", timeout=5) as response:
            if response.status != 200:
                raise ValueError("Ollama health check failed.")
            data = json.loads(response.read(1048576))
        names = {m.get("name") for m in data.get("models", []) if isinstance(m, dict)}
        if cfg["ollama_model"] not in names:
            raise ValueError("The configured model is not installed in Ollama.")
    print("PASS: selected prerequisites ready" + ("; Ollama was not checked." if args.skip_ollama else "."))
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (ValueError, OSError, subprocess.SubprocessError, KeyError, TypeError) as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        sys.exit(1)
