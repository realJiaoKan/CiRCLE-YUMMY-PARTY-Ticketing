<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function ticket_no_to_id(string $ticketNo): int
{
  $ticketNo = trim($ticketNo);
  if ($ticketNo === '')
    throw new RuntimeException('Missing ticket no');

  if (!preg_match('/(\\d+)$/', $ticketNo, $m)) {
    throw new RuntimeException('票号格式不正确（缺少数字部分）');
  }
  $id = (int) $m[1];
  if ($id <= 0) {
    throw new RuntimeException('票号数字部分不正确');
  }
  return $id;
}

function userdata_query_stats(PDO $pdo): array
{
  $stmt = $pdo->query('SELECT COUNT(*) AS total, COALESCE(SUM(checked = 1), 0) AS checked_count FROM tickets');
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $total = isset($row['total']) ? (int) $row['total'] : 0;
  $checked = isset($row['checked_count']) ? (int) $row['checked_count'] : 0;
  return ['total' => $total, 'checked_count' => $checked];
}

function userdata_get_by_no(string $no): array
{
  $pdo = checker_mysql_pdo();
  $id = ticket_no_to_id($no);
  $stmt = $pdo->prepare('SELECT ticket_id, name, email, checked FROM tickets WHERE ticket_id = ? LIMIT 1');
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) {
    return ['found' => false, 'no' => $no, 'userdata' => checker_mysql_hint()];
  }
  return [
    'found' => true,
    'no' => $no,
    'name' => (string) ($row['name'] ?? ''),
    'email' => (string) ($row['email'] ?? ''),
    'checked' => ((int) ($row['checked'] ?? 0)) === 1,
    'userdata' => checker_mysql_hint(),
  ];
}

function userdata_mark_checked(string $no): array
{
  $pdo = checker_mysql_pdo();
  $pdo->beginTransaction();
  try {
    $id = ticket_no_to_id($no);
    $stmt = $pdo->prepare('UPDATE tickets SET checked = 1 WHERE ticket_id = ? AND checked = 0');
    $stmt->execute([$id]);
    $changed = $stmt->rowCount() > 0;

    $exists = $pdo->prepare('SELECT 1 FROM tickets WHERE ticket_id = ? LIMIT 1');
    $exists->execute([$id]);
    $found = (bool) $exists->fetchColumn();
    if (!$found) {
      $pdo->rollBack();
      return ['found' => false, 'no' => $no, 'userdata' => checker_mysql_hint()];
    }

    $stats = userdata_query_stats($pdo);
    $pdo->commit();
    return ['found' => true, 'changed' => $changed, 'stats' => $stats, 'userdata' => checker_mysql_hint()];
  } catch (Throwable $e) {
    if ($pdo->inTransaction())
      $pdo->rollBack();
    throw $e;
  }
}

function userdata_mark_unchecked(string $no): array
{
  $pdo = checker_mysql_pdo();
  $pdo->beginTransaction();
  try {
    $id = ticket_no_to_id($no);
    $stmt = $pdo->prepare('UPDATE tickets SET checked = 0 WHERE ticket_id = ? AND checked = 1');
    $stmt->execute([$id]);
    $changed = $stmt->rowCount() > 0;

    $exists = $pdo->prepare('SELECT 1 FROM tickets WHERE ticket_id = ? LIMIT 1');
    $exists->execute([$id]);
    $found = (bool) $exists->fetchColumn();
    if (!$found) {
      $pdo->rollBack();
      return ['found' => false, 'no' => $no, 'userdata' => checker_mysql_hint()];
    }

    $stats = userdata_query_stats($pdo);
    $pdo->commit();
    return ['found' => true, 'changed' => $changed, 'stats' => $stats, 'userdata' => checker_mysql_hint()];
  } catch (Throwable $e) {
    if ($pdo->inTransaction())
      $pdo->rollBack();
    throw $e;
  }
}

function userdata_reset_all_unchecked(): array
{
  $pdo = checker_mysql_pdo();
  $pdo->beginTransaction();
  try {
    $stmt = $pdo->prepare('UPDATE tickets SET checked = 0 WHERE checked = 1');
    $stmt->execute();
    $changed = $stmt->rowCount();
    $stats = userdata_query_stats($pdo);
    $pdo->commit();
    return ['changed' => (int) $changed, 'stats' => $stats, 'userdata' => checker_mysql_hint()];
  } catch (Throwable $e) {
    if ($pdo->inTransaction())
      $pdo->rollBack();
    throw $e;
  }
}

function userdata_get_stats(): array
{
  $pdo = checker_mysql_pdo();
  return ['stats' => userdata_query_stats($pdo), 'userdata' => checker_mysql_hint()];
}
