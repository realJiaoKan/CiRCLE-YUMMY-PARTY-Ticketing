import csv
import time

from settings import LIST_PATH, COOLDOWN_SECONDS
from ticketing import generate_ticket


if __name__ == "__main__":
    processed = 0
    success = 0
    failures = []

    with LIST_PATH.open("r", encoding="utf-8-sig", newline="") as f:
        reader = csv.DictReader(f)
        for line_no, row in enumerate(reader, start=2):
            if not row:
                continue

            processed += 1
            try:
                name = row["name"].strip()
                email = row["email"].strip()
                table_no = row["table"].strip()
                generated = generate_ticket(name=name, email=email, table_no=table_no)
                print(
                    "line=%d ticket_no=%s name=%s email=%s table=%s"
                    % (line_no, generated["ticket_no"], name, email, table_no)
                )
                success += 1
            except Exception as exc:
                failures.append((line_no, row, str(exc)))
                print(
                    "[ALERT] line=%d failed reason=%s row=%s" % (line_no, str(exc), row)
                )

            time.sleep(COOLDOWN_SECONDS)

    print(
        "Batch done: total=%d success=%d failed=%d"
        % (processed, success, len(failures))
    )

    if failures:
        print("[ALERT] Failed CSV rows summary:")
        for line_no, row, reason in failures:
            print("  line=%d row=%s reason=%s" % (line_no, row, reason))
        raise SystemExit(2)

    raise SystemExit(0)
