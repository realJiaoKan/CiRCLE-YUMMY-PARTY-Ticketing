<?php
declare(strict_types=1);

function checker_base_dir(): string
{
  return dirname(__DIR__);
}

function checker_userdata_csv_path(): string
{
  return checker_base_dir() . DIRECTORY_SEPARATOR . 'userdata.csv';
}

function checker_public_pem_path(): string
{
  return checker_base_dir() . DIRECTORY_SEPARATOR . 'public.key';
}
