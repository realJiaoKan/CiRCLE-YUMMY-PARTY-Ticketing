from __future__ import annotations

import csv
import re
from contextlib import contextmanager
from pathlib import Path
from typing import IO, Iterator

try:
    import fcntl  # type: ignore
except Exception:  # pragma: no cover
    fcntl = None  # type: ignore


BASE_DIR = Path(__file__).resolve().parent.parent
USERDATA_FIELDS = ["no", "name", "email", "sig_b64", "checked"]
DEFAULT_USERDATA_CSV_PATH = BASE_DIR / "Web" / "userdata.csv"


@contextmanager
def _open_locked(path: Path, mode: str) -> Iterator[IO[str]]:
    path.parent.mkdir(parents=True, exist_ok=True)
    fp = open(path, mode, newline="", encoding="utf-8")
    try:
        if fcntl is not None:
            fcntl.flock(fp.fileno(), fcntl.LOCK_EX)
        yield fp
    finally:
        try:
            if fcntl is not None:
                fcntl.flock(fp.fileno(), fcntl.LOCK_UN)
        finally:
            fp.close()


def ensure_userdata_db(*, csv_path: Path = DEFAULT_USERDATA_CSV_PATH) -> None:
    with _open_locked(csv_path, "a+") as fp:
        fp.seek(0, 2)
        if fp.tell() == 0:
            writer = csv.DictWriter(fp, fieldnames=USERDATA_FIELDS)
            writer.writeheader()
            fp.flush()


def clear_userdata_db(*, csv_path: Path = DEFAULT_USERDATA_CSV_PATH) -> None:
    csv_path.parent.mkdir(parents=True, exist_ok=True)
    with _open_locked(csv_path, "w") as fp:
        writer = csv.DictWriter(fp, fieldnames=USERDATA_FIELDS)
        writer.writeheader()
        fp.flush()


def get_next_ticket_no(
    *, prefix: str, csv_path: Path = DEFAULT_USERDATA_CSV_PATH
) -> str:
    ensure_userdata_db(csv_path=csv_path)
    with _open_locked(csv_path, "r") as fp:
        last_no = ""
        reader = csv.DictReader(fp)
        for row in reader:
            last_no = (row.get("no") or "").strip()

    if not last_no:
        return f"{prefix}001"
    m = re.fullmatch(rf"{re.escape(prefix)}(\d{{3}})", last_no)
    if not m:
        raise ValueError(f"Unexpected ticket no in last CSV row: {last_no!r}")
    return f"{prefix}{int(m.group(1)) + 1:03d}"


def append_ticket(
    *,
    no: str,
    name: str,
    email: str,
    sig_b64: str,
    checked: str = "0",
    csv_path: Path = DEFAULT_USERDATA_CSV_PATH,
) -> None:
    ensure_userdata_db(csv_path=csv_path)
    with _open_locked(csv_path, "a") as fp:
        writer = csv.DictWriter(fp, fieldnames=USERDATA_FIELDS)
        writer.writerow(
            {
                "no": no,
                "name": name,
                "email": email,
                "sig_b64": sig_b64,
                "checked": checked,
            }
        )
        fp.flush()
