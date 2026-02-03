import secrets
from database import insert_checker_code


def generate_checkers(*, count: int, length_bytes: int = 24):
    """
    Generate `count` strong random checker codes, insert into MySQL, and return them.

    Uses URL-safe base64 without padding (similar to browser-generated strong passwords).
    """
    target = int(count)
    if target <= 0:
        raise ValueError("count must be > 0")

    n = int(length_bytes)
    if n <= 0:
        raise ValueError("length_bytes must be > 0")

    created: list[str] = []
    seen = set()
    while len(created) < target:
        code = secrets.token_urlsafe(n)
        if code in seen:
            continue
        seen.add(code)
        if insert_checker_code(code=code, note=None):
            created.append(code)

    return created


if __name__ == "__main__":
    print(generate_checkers(count=int(input("Checkers to generate (persons): "))))
