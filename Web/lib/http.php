<?php
declare(strict_types=1);

function json_response(array $data, int $status = 200): void
{
  http_response_code($status);
  header('content-type: application/json; charset=utf-8');
  header('cache-control: no-store');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function json_ok(array $data = []): void
{
  json_response(['ok' => true] + $data, 200);
}

function json_fail(string $message, int $status = 400, array $extra = []): void
{
  json_response(['ok' => false, 'message' => $message] + $extra, $status);
}

function read_json_body(): array
{
  $raw = file_get_contents('php://input');
  if ($raw === false)
    return [];
  $raw = trim($raw);
  if ($raw === '')
    return [];
  $data = json_decode($raw, true);
  if (!is_array($data))
    json_fail('Invalid JSON body', 400);
  return $data;
}

