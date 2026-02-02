import subprocess
import base64

from settings import BASE_DIR

PRIVATE_KEY_PATH = BASE_DIR / "Ticketing" / "private.key"
PUBLIC_KEY_PATH = BASE_DIR / "Web" / "public.key"


def _run_openssl(args: list[str], *, input_bytes: bytes | None = None) -> bytes:
    proc = subprocess.run(
        ["openssl", *args],
        input=input_bytes,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if proc.returncode != 0:
        stderr = (proc.stderr or b"").decode("utf-8", errors="replace")
        raise RuntimeError(f"OpenSSL failed: openssl {' '.join(args)}\n{stderr}")
    return proc.stdout


def _der_read_length(data: bytes, offset: int) -> tuple[int, int]:
    if offset >= len(data):
        raise ValueError("DER: truncated length")
    first = data[offset]
    offset += 1
    if first < 0x80:
        return (first, offset)
    n = first & 0x7F
    if n == 0 or n > 4 or offset + n > len(data):
        raise ValueError("DER: invalid length")
    length = int.from_bytes(data[offset : offset + n], "big")
    return (length, offset + n)


def _ecdsa_der_to_raw_p256(sig_der: bytes) -> bytes:
    if not sig_der or sig_der[0] != 0x30:
        raise ValueError("ECDSA DER: expected SEQUENCE")
    seq_len, off = _der_read_length(sig_der, 1)
    if off + seq_len != len(sig_der):
        raise ValueError("ECDSA DER: length mismatch")

    if off >= len(sig_der) or sig_der[off] != 0x02:
        raise ValueError("ECDSA DER: expected INTEGER r")
    r_len, off = _der_read_length(sig_der, off + 1)
    r = sig_der[off : off + r_len]
    off += r_len

    if off >= len(sig_der) or sig_der[off] != 0x02:
        raise ValueError("ECDSA DER: expected INTEGER s")
    s_len, off = _der_read_length(sig_der, off + 1)
    s = sig_der[off : off + s_len]
    off += s_len
    if off != len(sig_der):
        raise ValueError("ECDSA DER: trailing bytes")

    r_int = int.from_bytes(r, "big")
    s_int = int.from_bytes(s, "big")
    return r_int.to_bytes(32, "big") + s_int.to_bytes(32, "big")


def generate_keypair(*, private_key_path, public_key_path):
    if private_key_path.exists() or public_key_path.exists():
        print(f"Keys already exist: {private_key_path}, {public_key_path}, skipping.")
        return

    _run_openssl(
        [
            "genpkey",
            "-algorithm",
            "EC",
            "-pkeyopt",
            "ec_paramgen_curve:P-256",
            "-pkeyopt",
            "ec_param_enc:named_curve",
            "-out",
            str(private_key_path),
        ]
    )
    _run_openssl(
        ["pkey", "-in", str(private_key_path), "-pubout", "-out", str(public_key_path)]
    )


def sign(*, private_key_path, message: bytes, b64url: bool = False):
    sig_der = _run_openssl(
        ["dgst", "-sha256", "-sign", str(private_key_path)], input_bytes=message
    )
    sig_raw = _ecdsa_der_to_raw_p256(sig_der)
    if b64url:
        return base64.urlsafe_b64encode(sig_raw).decode("ascii").rstrip("=")
    return sig_raw


if __name__ == "__main__":
    generate_keypair(private_key_path=PRIVATE_KEY_PATH, public_key_path=PUBLIC_KEY_PATH)
