<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

checker_logout();
header('location: ./login.php');
exit;

