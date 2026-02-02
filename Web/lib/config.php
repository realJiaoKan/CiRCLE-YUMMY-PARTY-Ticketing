<?php
declare(strict_types=1);

function checker_base_dir(): string
{
  return dirname(__DIR__);
}

function checker_public_pem_path(): string
{
  return checker_base_dir() . DIRECTORY_SEPARATOR . 'public.key';
}

function checker_web_config_local_path(): string
{
  return checker_base_dir() . DIRECTORY_SEPARATOR . 'config.local.php';
}

function checker_mysql_config(): array
{
  $path = checker_web_config_local_path();

  if (!is_file($path)) {
    throw new RuntimeException("缺少 MySQL 配置文件：请创建 " . $path);
  }

  $cfg = require $path;
  if (!is_array($cfg)) {
    throw new RuntimeException("MySQL 配置文件返回值必须是 array: " . $path);
  }
  $mysql = isset($cfg['mysql']) && is_array($cfg['mysql']) ? $cfg['mysql'] : $cfg;

  $host = isset($mysql['host']) ? (string) $mysql['host'] : '';
  $port = isset($mysql['port']) ? (int) $mysql['port'] : 3306;
  $username = isset($mysql['username']) ? (string) $mysql['username'] : '';
  $password = isset($mysql['password']) ? (string) $mysql['password'] : '';
  $database = isset($mysql['database']) ? (string) $mysql['database'] : '';

  if (trim($host) === '' || trim($username) === '' || trim($database) === '') {
    throw new RuntimeException('MySQL 配置不完整：需要 host/username/database（来源：' . $path . '）');
  }
  return [
    'host' => $host,
    'port' => $port,
    'username' => $username,
    'password' => $password,
    'database' => $database,
  ];
}

function checker_mysql_hint(): string
{
  $c = checker_mysql_config();
  return $c['host'] . ':' . (string) $c['port'] . '/' . $c['database'];
}

function checker_mysql_pdo(): PDO
{
  static $pdo = null;
  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $c = checker_mysql_config();
  $dsn = 'mysql:host=' . $c['host'] . ';port=' . (string) $c['port'] . ';dbname=' . $c['database'] . ';charset=utf8mb4';
  $pdo = new PDO($dsn, $c['username'], $c['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  return $pdo;
}
