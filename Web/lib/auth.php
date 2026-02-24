<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function checker_token_ttl_seconds(): int
{
  return 3600;
}

function checker_token_hash(string $token): string
{
  return hash('sha256', $token);
}

function checker_ensure_tokens_table(PDO $pdo): void
{
  static $ensured = false;
  if ($ensured)
    return;

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS checker_tokens (
      token_hash CHAR(64) NOT NULL PRIMARY KEY,
      checker_code VARCHAR(255) NOT NULL,
      expires_at DATETIME NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
  $ensured = true;
}

function checker_delete_expired_tokens(PDO $pdo): void
{
  $pdo->exec("DELETE FROM checker_tokens WHERE expires_at <= UTC_TIMESTAMP()");
}

function checker_issue_login_token(string $checkerCode): array
{
  $checkerCode = trim($checkerCode);
  if ($checkerCode === '')
    throw new RuntimeException('checker code is empty');

  $pdo = checker_mysql_pdo();
  checker_ensure_tokens_table($pdo);
  checker_delete_expired_tokens($pdo);

  $token = bin2hex(random_bytes(32));
  $ttl = checker_token_ttl_seconds();
  $stmt = $pdo->prepare(
    'INSERT INTO checker_tokens(token_hash, checker_code, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))'
  );
  $stmt->execute([checker_token_hash($token), $checkerCode, $ttl]);
  return ['token' => $token, 'expires_in' => $ttl];
}

function checker_is_valid_token(string $token): bool
{
  $token = trim($token);
  if ($token === '')
    return false;

  $pdo = checker_mysql_pdo();
  checker_ensure_tokens_table($pdo);
  checker_delete_expired_tokens($pdo);

  $stmt = $pdo->prepare(
    'SELECT token_hash FROM checker_tokens WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1'
  );
  $stmt->execute([checker_token_hash($token)]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return is_array($row);
}

function checker_read_api_token(): string
{
  $xToken = isset($_SERVER['HTTP_X_CHECKER_TOKEN']) ? (string) $_SERVER['HTTP_X_CHECKER_TOKEN'] : '';
  $xToken = trim($xToken);
  if ($xToken !== '')
    return $xToken;

  $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? (string) $_SERVER['HTTP_AUTHORIZATION'] : '';
  if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
    $bearer = trim((string) ($m[1] ?? ''));
    if ($bearer !== '')
      return $bearer;
  }
  return '';
}

function checker_revoke_token(string $token): void
{
  $token = trim($token);
  if ($token === '')
    return;

  $pdo = checker_mysql_pdo();
  checker_ensure_tokens_table($pdo);
  $stmt = $pdo->prepare('DELETE FROM checker_tokens WHERE token_hash = ?');
  $stmt->execute([checker_token_hash($token)]);
}

function checker_require_page_token(): string
{
  $token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
  if ($token !== '' && checker_is_valid_token($token))
    return $token;

  $next = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
  $next = preg_replace('/([?&])token=[^&]*(&)?/', '$1', $next);
  if (is_string($next)) {
    $next = str_replace('?&', '?', $next);
    $next = str_replace('&&', '&', $next);
    $next = rtrim($next, '?&');
  } else {
    $next = '';
  }

  $q = $next !== '' ? ('?next=' . rawurlencode($next)) : '';
  header('location: ./login.php' . $q);
  exit;
}

function checker_require_api_auth(): void
{
  $token = checker_read_api_token();
  if ($token !== '' && checker_is_valid_token($token))
    return;

  http_response_code(401);
  header('content-type: application/json; charset=utf-8');
  header('cache-control: no-store');
  echo json_encode(['ok' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
  exit;
}

function checker_logout(): void
{
  $token = isset($_GET['token']) ? (string) $_GET['token'] : '';
  $token = trim($token);
  if ($token === '')
    $token = checker_read_api_token();
  checker_revoke_token($token);
}

function checker_sanitize_next(string $next): string
{
  $next = trim($next);
  if ($next === '')
    return './index.php';
  if (strpos($next, "\n") !== false || strpos($next, "\r") !== false)
    return './index.php';
  if (preg_match('#^https?://#i', $next) || strncmp($next, '//', 2) === 0)
    return './index.php';
  return $next;
}

function checker_append_token_to_next(string $next, string $token): string
{
  $next = checker_sanitize_next($next);
  $next = preg_replace('/([?&])token=[^&]*(&)?/', '$1', $next);
  if (!is_string($next))
    $next = './index.php';
  $next = str_replace('?&', '?', $next);
  $next = str_replace('&&', '&', $next);
  $next = rtrim($next, '?&');
  $sep = strpos($next, '?') === false ? '?' : '&';
  return $next . $sep . 'token=' . rawurlencode($token);
}

function checker_authenticate_code(string $code): array
{
  $code = trim($code);
  if ($code === '')
    return ['ok' => false, 'message' => '请输入检票密码'];

  $pdo = checker_mysql_pdo();
  try {
    $stmt = $pdo->prepare('SELECT code FROM checkers WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
      return ['ok' => false, 'message' => '密码不正确'];
    }
  } catch (PDOException $e) {
    $msg = $e->getMessage();
    if ($e->getCode() === '42S02' || strpos($msg, 'checkers') !== false) {
      return ['ok' => false, 'message' => '缺少检票人员数据表'];
    }
    throw $e;
  }

  return ['ok' => true, 'code' => $code];
}
