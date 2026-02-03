import json
import pymysql

from settings import BASE_DIR, SCHEMA_SQL_PATH


def _load_mysql_config() -> dict:
    config = json.loads(
        (BASE_DIR / "Ticketing" / "config.json").read_text(encoding="utf-8")
    )
    return config["mysql"]


def _connect():
    mysql = _load_mysql_config()
    return pymysql.connect(
        host=str(mysql["host"]),
        port=int(mysql["port"]),
        user=str(mysql["username"]),
        password=str(mysql["password"]),
        charset="utf8mb4",
        autocommit=False,
        cursorclass=pymysql.cursors.Cursor,
    )


def _connect_db():
    mysql = _load_mysql_config()
    return pymysql.connect(
        host=str(mysql["host"]),
        port=int(mysql["port"]),
        user=str(mysql["username"]),
        password=str(mysql["password"]),
        database=str(mysql["database"]),
        charset="utf8mb4",
        autocommit=False,
        cursorclass=pymysql.cursors.Cursor,
    )


def _ensure_database_exists(*, conn) -> None:
    mysql = _load_mysql_config()
    db = str(mysql["database"])
    with conn.cursor() as cur:
        cur.execute(
            f"CREATE DATABASE IF NOT EXISTS `{db}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        )
    conn.commit()


def format_ticket_no(*, ticket_id: int) -> str:
    mysql = _load_mysql_config()
    prefix = str(mysql["ticket_no_prefix"]).strip()
    length = int(mysql["ticket_no_length"])
    return f"{prefix}{int(ticket_id):0{length}d}"


def ensure_database():
    with _connect() as conn:
        _ensure_database_exists(conn=conn)
        conn.select_db(str(_load_mysql_config()["database"]))

        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = 'checkers'
                """
            )
            (checkers_exists,) = cur.fetchone() or (0,)

            cur.execute(
                """
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = 'tickets'
                """
            )
            (exists,) = cur.fetchone() or (0,)
            if int(exists) == 1:
                cur.execute(
                    """
                    SELECT COUNT(*)
                    FROM information_schema.columns
                    WHERE table_schema = DATABASE()
                      AND table_name = 'tickets'
                      AND column_name IN ('ticket_id', 'name', 'email', 'sig_b64', 'checked')
                    """
                )
                (cols,) = cur.fetchone() or (0,)
                if int(cols) == 5:
                    if int(checkers_exists) == 1:
                        cur.execute(
                            """
                            SELECT COUNT(*)
                            FROM information_schema.columns
                            WHERE table_schema = DATABASE()
                              AND table_name = 'checkers'
                              AND column_name IN ('checker_id', 'code')
                            """
                        )
                        (checker_cols,) = cur.fetchone() or (0,)
                        if int(checker_cols) == 2:
                            return
                        raise RuntimeError(
                            "Detected existing `checkers` table with incompatible schema. "
                            "Please migrate/drop it and re-apply Ticketing/schema.mysql.sql."
                        )

                    cur.execute(
                        """
                        CREATE TABLE
                          IF NOT EXISTS checkers (
                            checker_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                            note VARCHAR(255) NULL,
                            code VARCHAR(255) NOT NULL,
                            UNIQUE KEY uniq_code (code),
                            KEY idx_code (code)
                          ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
                        """
                    )
                    conn.commit()
                    return
                raise RuntimeError(
                    "Detected existing `tickets` table with incompatible schema. "
                    "Please migrate/drop it and re-apply Ticketing/schema.mysql.sql."
                )

            if not SCHEMA_SQL_PATH.is_file():
                raise RuntimeError(f"Missing schema file: {SCHEMA_SQL_PATH}")

            sql = SCHEMA_SQL_PATH.read_text(encoding="utf-8")
            for stmt in [s.strip() for s in sql.split(";")]:
                if stmt:
                    cur.execute(stmt)
        conn.commit()


def clear_userdata_db() -> None:
    ensure_database()
    with _connect_db() as conn:
        with conn.cursor() as cur:
            cur.execute("TRUNCATE TABLE tickets")
        conn.commit()


def create_ticket(*, name: str, email: str, signer, checked: str = "0"):
    ensure_database()
    checked_int = 1 if str(checked).strip() == "1" else 0
    with _connect_db() as conn:
        try:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    INSERT INTO tickets(name, email, sig_b64, checked)
                    VALUES (%s, %s, %s, %s)
                    """,
                    (name, email, "", checked_int),
                )
                ticket_id = int(cur.lastrowid)
                ticket_no = format_ticket_no(ticket_id=ticket_id)
                sig_b64 = str(signer(ticket_no))
                cur.execute(
                    "UPDATE tickets SET sig_b64 = %s WHERE ticket_id = %s",
                    (sig_b64, ticket_id),
                )
            conn.commit()
            return {"ticket_no": ticket_no, "sig_b64": sig_b64}
        except Exception:
            conn.rollback()
            raise


def insert_checker_code(*, code: str, note=None) -> bool:
    ensure_database()
    code = str(code).strip()
    if not code:
        raise ValueError("Missing checker code")
    note = None if note is None else str(note).strip() or None
    with _connect_db() as conn:
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "INSERT IGNORE INTO checkers(code, note) VALUES (%s, %s)",
                    (code, note),
                )
                inserted = cur.rowcount > 0
            conn.commit()
            return inserted
        except Exception:
            conn.rollback()
            raise


if __name__ == "__main__":
    input(
        "This will CLEAR ALL DATA in the userdata database. Press Enter to continue..."
        "if not, press Ctrl+C to abort."
    )
    clear_userdata_db()
