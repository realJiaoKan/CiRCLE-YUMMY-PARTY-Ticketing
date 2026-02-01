<?php
declare(strict_types=1);

require_once __DIR__ . '/csv_store.php';

function userdata_get_by_no(string $no): array
{
  [$fp, $path] = csv_open_userdata_locked();
  try {
    $rows = csv_normalize_userdata_rows(csv_read_all_rows($fp));
    $header = $rows[0];
    $idxName = (int) array_search('name', $header, true);
    $idxEmail = (int) array_search('email', $header, true);
    $idxChecked = (int) array_search('checked', $header, true);

    $rowIndex = csv_find_row_by_no($rows, $no);
    if ($rowIndex < 0) {
      return ['found' => false, 'no' => $no, 'userdata' => $path];
    }

    $row = $rows[$rowIndex];
    $name = isset($row[$idxName]) ? (string) $row[$idxName] : '';
    $email = isset($row[$idxEmail]) ? (string) $row[$idxEmail] : '';
    $checked = trim((string) ($row[$idxChecked] ?? '0')) === '1';
    return [
      'found' => true,
      'no' => $no,
      'name' => $name,
      'email' => $email,
      'checked' => $checked,
      'userdata' => $path,
    ];
  } finally {
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}

function userdata_mark_checked(string $no): array
{
  [$fp, $path] = csv_open_userdata_locked();
  try {
    $rows = csv_normalize_userdata_rows(csv_read_all_rows($fp));
    $header = $rows[0];
    $idxChecked = (int) array_search('checked', $header, true);
    $rowIndex = csv_find_row_by_no($rows, $no);
    if ($rowIndex < 0) {
      return ['found' => false, 'no' => $no, 'userdata' => $path];
    }

    $was = trim((string) ($rows[$rowIndex][$idxChecked] ?? '0')) === '1';
    if (!$was) {
      $rows[$rowIndex][$idxChecked] = '1';
      csv_write_all_rows($fp, $rows);
    }

    return ['found' => true, 'changed' => !$was, 'stats' => csv_stats($rows), 'userdata' => $path];
  } finally {
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}

function userdata_mark_unchecked(string $no): array
{
  [$fp, $path] = csv_open_userdata_locked();
  try {
    $rows = csv_normalize_userdata_rows(csv_read_all_rows($fp));
    $header = $rows[0];
    $idxChecked = array_search('checked', $header, true);
    if ($idxChecked === false) {
      throw new RuntimeException('userdata.csv 缺少 checked 列');
    }
    $idxChecked = (int) $idxChecked;
    $rowIndex = csv_find_row_by_no($rows, $no);
    if ($rowIndex < 0) {
      return ['found' => false, 'no' => $no, 'userdata' => $path];
    }

    $was = trim((string) ($rows[$rowIndex][$idxChecked] ?? '0')) === '1';
    $rows[$rowIndex][$idxChecked] = '0';
    csv_write_all_rows($fp, $rows);
    return ['found' => true, 'changed' => $was, 'stats' => csv_stats($rows), 'userdata' => $path];
  } finally {
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}

function userdata_reset_all_unchecked(): array
{
  [$fp, $path] = csv_open_userdata_locked();
  try {
    $rows = csv_normalize_userdata_rows(csv_read_all_rows($fp));
    $header = $rows[0];
    $idxChecked = array_search('checked', $header, true);
    if ($idxChecked === false) {
      throw new RuntimeException('userdata.csv 缺少 checked 列');
    }
    $idxChecked = (int) $idxChecked;

    $changed = 0;
    for ($i = 1; $i < count($rows); $i++) {
      $cur = trim((string) ($rows[$i][$idxChecked] ?? '0'));
      if ($cur !== '0') {
        $changed++;
      }
      $rows[$i][$idxChecked] = '0';
    }
    csv_write_all_rows($fp, $rows);
    return ['changed' => $changed, 'stats' => csv_stats($rows), 'userdata' => $path];
  } finally {
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}

function userdata_get_stats(): array
{
  [$fp, $path] = csv_open_userdata_locked();
  try {
    $rows = csv_normalize_userdata_rows(csv_read_all_rows($fp));
    return ['stats' => csv_stats($rows), 'userdata' => $path];
  } finally {
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}

