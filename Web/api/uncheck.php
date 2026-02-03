<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/userdata_store.php';

checker_require_api_auth();

$body = read_json_body();
$no = isset($body['no']) ? trim((string) $body['no']) : '';
if ($no === '')
  json_fail('Missing no', 400);

try {
  $ret = userdata_mark_unchecked($no);
  if (!($ret['found'] ?? false)) {
    json_fail('No not found', 404, ['no' => $no, 'userdata' => $ret['userdata'] ?? null]);
  }
  json_ok(['changed' => (bool) ($ret['changed'] ?? false), 'stats' => $ret['stats'] ?? null]);
} catch (Throwable $e) {
  json_fail($e->getMessage(), 400);
}
