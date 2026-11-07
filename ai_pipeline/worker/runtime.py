"""Atomic state, OS-managed locks, and bounded child processes (standard library)."""
import json
import os
from pathlib import Path
import signal
import subprocess
from contextlib import contextmanager


def write_json(path, value):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    temp = path.with_name(path.name + ".tmp")
    with temp.open("w", encoding="utf-8", newline="\n") as stream:
        json.dump(value, stream, indent=2, ensure_ascii=True)
        stream.write("\n")
        stream.flush()
        os.fsync(stream.fileno())
    os.replace(temp, path)


@contextmanager
def exclusive_lock(path):
    """The OS releases this lock after process death; the file itself is retained."""
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    stream = path.open("a+b")
    stream.seek(0, 2)
    if stream.tell() == 0:
        stream.write(b"0")
        stream.flush()
    stream.seek(0)
    locked = False
    try:
        if os.name == "nt":
            import msvcrt
            msvcrt.locking(stream.fileno(), msvcrt.LK_NBLCK, 1)
        else:
            import fcntl
            fcntl.flock(stream, fcntl.LOCK_EX | fcntl.LOCK_NB)
        locked = True
        yield
    finally:
        if locked:
            if os.name == "nt":
                import msvcrt
                stream.seek(0)
                msvcrt.locking(stream.fileno(), msvcrt.LK_UNLCK, 1)
            else:
                import fcntl
                fcntl.flock(stream, fcntl.LOCK_UN)
        stream.close()


def run_command(argv, cwd, log, timeout, env=None):
    """Never interpolate task text into a shell; terminate the child tree on timeout."""
    kwargs = {"start_new_session": True} if os.name != "nt" else {"creationflags": subprocess.CREATE_NEW_PROCESS_GROUP}
    with Path(log).open("ab") as stream:
        child = subprocess.Popen(argv, cwd=cwd, stdout=stream, stderr=subprocess.STDOUT,
                                 stdin=subprocess.DEVNULL, env=env, **kwargs)
        try:
            code = child.wait(timeout=timeout)
        except BaseException:
            if os.name == "nt":
                killer = subprocess.run(["taskkill.exe", "/PID", str(child.pid), "/T", "/F"],
                                        stdout=stream, stderr=stream, timeout=30)
                if killer.returncode != 0 and child.poll() is None:
                    raise RuntimeError("Could not stop child tree; stop it manually before retrying.")
            else:
                try:
                    os.killpg(child.pid, signal.SIGKILL)
                except ProcessLookupError:
                    pass
            child.wait(timeout=30)
            raise
    if code:
        raise RuntimeError(f"Command exited with status {code}; see {log}")
    return code
