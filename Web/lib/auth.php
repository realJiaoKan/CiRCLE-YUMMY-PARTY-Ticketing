<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function checker_session_start(): void
{
  if (session_status() === PHP_SESSION_ACTIVE)
    return;
  session_start();
}

function checker_is_authenticated(): bool
{
  checker_session_start();
  return isset($_SESSION['checker_code']) && is_string($_SESSION['checker_code']) && trim((string) $_SESSION['checker_code']) !== '';
}

function checker_require_page_auth(): void
{
  if (checker_is_authenticated())
    return;

  $next = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
  $q = $next !== '' ? ('?next=' . rawurlencode($next)) : '';
  header('location: ./login.php' . $q);
  exit;
}

function checker_require_api_auth(): void
{
  if (checker_is_authenticated())
    return;

  http_response_code(401);
  header('content-type: application/json; charset=utf-8');
  header('cache-control: no-store');
  echo json_encode(['ok' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
  exit;
}

function checker_logout(): void
{
  checker_session_start();
  $_SESSION = [];
  if (session_id() !== '')
    session_destroy();
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

function checker_authenticate_code(string $code): array
{
  $code = trim($code);
  if ($code === '')
    return ['ok' => false, 'message' => '请输入检票密码'];

  $pdo = checker_mysql_pdo();
  try {
    $stmt = $pdo->prepare('SELECT checker_id FROM checkers WHERE code = ? LIMIT 1');
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
