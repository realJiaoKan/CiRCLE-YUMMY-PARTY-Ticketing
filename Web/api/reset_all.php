<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/userdata_store.php';

checker_require_api_auth();

try {
  $ret = userdata_reset_all_unchecked();
  json_ok(['changed' => (int) ($ret['changed'] ?? 0), 'stats' => $ret['stats'] ?? null, 'userdata' => $ret['userdata'] ?? null]);
} catch (Throwable $e) {
  json_fail($e->getMessage(), 400);
}
