<?php
declare(strict_types=1);

function parse_qr_payload(string $raw): array
{
  $raw = trim(str_replace("\u{200B}", '', $raw));
  if ($raw === '') {
    throw new RuntimeException('二维码内容为空');
  }
  // Hardcoded format: "<no>,<sig>" (comma only, also accept full-width comma)
  $parts = preg_split('/[，,]/u', $raw);
  if ($parts === false)
    $parts = [];
  $parts = array_values(array_filter(array_map('trim', $parts), static fn($p) => $p !== ''));
  if (count($parts) !== 2) {
    throw new RuntimeException('二维码格式不正确');
  }
  [$no, $sig] = $parts;
  if ($no === '')
    throw new RuntimeException('缺少番号');
  if ($sig === '')
    throw new RuntimeException('缺少签名');
  return ['no' => $no, 'sig' => $sig];
}

