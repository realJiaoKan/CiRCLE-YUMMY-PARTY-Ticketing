<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/userdata_store.php';
require_once __DIR__ . '/../lib/qr_payload.php';
require_once __DIR__ . '/../lib/crypto_verify.php';

checker_require_api_auth();

$body = read_json_body();
$raw = isset($body['raw']) ? (string) $body['raw'] : '';
if (trim($raw) === '')
  json_fail('Missing raw', 400);

try {
  $parsed = parse_qr_payload($raw);
  $no = $parsed['no'];
  $sigB64u = $parsed['sig'];

  $info = userdata_get_by_no($no);
  if (!($info['found'] ?? false)) {
    json_fail('番号不存在', 404, ['no' => $no, 'userdata' => $info['userdata'] ?? null]);
  }
  $name = (string) ($info['name'] ?? '');
  $email = (string) ($info['email'] ?? '');
  $checked = (bool) ($info['checked'] ?? false);

  // Verify signature (ES256 raw r||s base64url)
  $sigRaw = b64url_decode_bytes($sigB64u);
  if (strlen($sigRaw) !== 64) {
    json_fail('签名长度不正确（期望 64 字节 ES256 原始签名）', 400);
  }
  $sigOk = verify_es256_rawsig($no, $sigRaw);
  if (!$sigOk) {
    json_ok([
      'status' => 'fail',
      'no' => $no,
      'name' => $name,
      'email' => $email,
      'checked' => $checked,
      'message' => '签名不匹配',
    ]);
  }

  $mark = userdata_mark_checked($no);
  if (!($mark['found'] ?? false)) {
    json_fail('番号不存在', 404, ['no' => $no, 'userdata' => $mark['userdata'] ?? null]);
  }
  $already = !($mark['changed'] ?? false);
  $checked = true;

  json_ok([
    'status' => $already ? 'already' : 'pass',
    'already' => $already,
    'no' => $no,
    'name' => $name,
    'email' => $email,
    'checked' => $checked,
    'stats' => $mark['stats'] ?? null,
  ]);
} catch (Throwable $e) {
  json_fail($e->getMessage(), 400);
}
