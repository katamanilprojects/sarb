"""Create an isolated Python runtime outside the web root; no third-party packages."""
import subprocess
import sys
from pathlib import Path
import venv
from config import PIPELINE_DIR, load_config


def main():
    if sys.version_info < (3, 10):
        raise ValueError("Python 3.10+ is required.")
    cfg = load_config()
    folder = Path(cfg["runtime_dir"]) / "venv"
    if not folder.exists():
        venv.EnvBuilder(with_pip=False).create(folder)
    python = folder / ("Scripts/python.exe" if sys.platform == "win32" else "bin/python")
    if not python.is_file():
        raise ValueError("Incomplete virtual environment; inspect it before recreating.")
    subprocess.run([str(python), "-m", "unittest", "discover", "-s", str(PIPELINE_DIR / "tests"), "-p", "test_*.py"], check=True)
    subprocess.run([str(python), str(PIPELINE_DIR / "worker/test_environment.py")], check=True, timeout=90)
    print(f"Setup checks passed. Set AI_PIPELINE_PYTHON to {python}")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as exc:
        print(f"Setup failed: {exc}", file=sys.stderr)
        sys.exit(1)
