<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function b64url_decode_bytes(string $s): string
{
  $s = trim($s);
  $s = str_replace(['-', '_'], ['+', '/'], $s);
  $pad = strlen($s) % 4;
  if ($pad !== 0)
    $s .= str_repeat('=', 4 - $pad);
  $out = base64_decode($s, true);
  if ($out === false) {
    throw new RuntimeException('签名不是有效的 base64/base64url');
  }
  return $out;
}

function der_len(int $len): string
{
  if ($len < 0x80)
    return chr($len);
  $b = ltrim(pack('N', $len), "\x00");
  return chr(0x80 | strlen($b)) . $b;
}

function der_int_from_bytes(string $b): string
{
  // Strip leading zeros
  while (strlen($b) > 1 && $b[0] === "\x00" && (ord($b[1]) & 0x80) === 0) {
    $b = substr($b, 1);
  }
  // If high bit set, prefix 0x00 to keep it positive.
  if ((ord($b[0]) & 0x80) !== 0) {
    $b = "\x00" . $b;
  }
  return "\x02" . der_len(strlen($b)) . $b;
}

function ecdsa_raw_to_der_p256(string $raw64): string
{
  if (strlen($raw64) !== 64) {
    throw new RuntimeException('签名长度不正确（期望 64 字节 ES256 签名）');
  }
  $r = substr($raw64, 0, 32);
  $s = substr($raw64, 32, 32);
  $rEnc = der_int_from_bytes($r);
  $sEnc = der_int_from_bytes($s);
  $body = $rEnc . $sEnc;
  return "\x30" . der_len(strlen($body)) . $body;
}

function load_public_pem(): string
{
  $pemPath = checker_public_pem_path();
  if (is_file($pemPath)) {
    $pem = file_get_contents($pemPath);
    if ($pem === false || trim($pem) === '') {
      throw new RuntimeException("无法读取公钥文件");
    }
    return $pem;
  }
  throw new RuntimeException("缺少公钥文件");
}

function verify_es256_rawsig(string $message, string $sig_raw64): bool
{
  $pem = load_public_pem();
  $pub = openssl_pkey_get_public($pem);
  if ($pub === false) {
    throw new RuntimeException('公钥无法解析');
  }
  $sigDer = ecdsa_raw_to_der_p256($sig_raw64);
  $ok = openssl_verify($message, $sigDer, $pub, OPENSSL_ALGO_SHA256);
  if ($ok === 1)
    return true;
  if ($ok === 0)
    return false;
  throw new RuntimeException('OpenSSL 验签失败');
}
