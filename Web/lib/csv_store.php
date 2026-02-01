<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Provides locked read/write for userdata.csv with "checked" column support.
 *
 * CSV header must contain at least: no,name,email,sig_b64 and optionally checked.
 * checked is "0"/"1".
 */

function csv_open_userdata_locked(): array
{
  $path = checker_userdata_csv_path();
  $dir = dirname($path);
  if (!is_dir($dir)) {
    throw new RuntimeException("用户数据文件目录不存在");
  }
  if (is_file($path) && !is_writable($path)) {
    throw new RuntimeException("无法写入用户数据文件");
  }
  if (!is_file($path) && !is_writable($dir)) {
    throw new RuntimeException("无法创建用户数据文件");
  }
  $fp = @fopen($path, 'c+');
  if ($fp === false) {
    $hint = "无法打开用户数据文件。请检查文件/目录权限";
    throw new RuntimeException($hint);
  }
  if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    throw new RuntimeException("无法锁定用户数据文件");
  }
  return [$fp, $path];
}

function csv_read_all_rows($fp): array
{
  rewind($fp);
  $rows = [];
  while (($row = fgetcsv($fp)) !== false) {
    $rows[] = $row;
  }
  return $rows;
}

function csv_write_all_rows($fp, array $rows): void
{
  ftruncate($fp, 0);
  rewind($fp);
  foreach ($rows as $row) {
    fputcsv($fp, $row);
  }
  fflush($fp);
}

function csv_normalize_userdata_rows(array $rows): array
{
  if (count($rows) === 0) {
    $rows = [['no', 'name', 'email', 'sig_b64', 'checked']];
  }

  $header = array_map(static fn($v) => trim((string) $v), $rows[0]);
  $idxNo = array_search('no', $header, true);
  if ($idxNo === false) {
    throw new RuntimeException('用户数据文件缺少 no 列');
  }

  $idxChecked = array_search('checked', $header, true);
  if ($idxChecked === false) {
    $header[] = 'checked';
    $idxChecked = count($header) - 1;
  }
  $rows[0] = $header;

  // normalize row length
  for ($i = 1; $i < count($rows); $i++) {
    while (count($rows[$i]) < count($header))
      $rows[$i][] = '';
    if (!isset($rows[$i][$idxChecked]) || trim((string) $rows[$i][$idxChecked]) === '') {
      $rows[$i][$idxChecked] = '0';
    }
  }

  return $rows;
}

function csv_find_row_by_no(array $rows, string $no): int
{
  $header = $rows[0];
  $idxNo = array_search('no', $header, true);
  if ($idxNo === false)
    return -1;
  for ($i = 1; $i < count($rows); $i++) {
    if (trim((string) ($rows[$i][$idxNo] ?? '')) === $no)
      return $i;
  }
  return -1;
}

function csv_stats(array $rows): array
{
  $header = $rows[0];
  $idxChecked = array_search('checked', $header, true);
  $checked = 0;
  $total = max(0, count($rows) - 1);
  if ($idxChecked !== false) {
    for ($i = 1; $i < count($rows); $i++) {
      $v = trim((string) ($rows[$i][(int) $idxChecked] ?? ''));
      if ($v === '1')
        $checked++;
    }
  }
  return ['total' => $total, 'checked_count' => $checked];
}
