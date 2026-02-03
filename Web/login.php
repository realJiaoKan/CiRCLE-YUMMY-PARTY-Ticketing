<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

$next = '';
if (isset($_GET['next']))
  $next = (string) $_GET['next'];
if (isset($_POST['next']))
  $next = (string) $_POST['next'];
$next = checker_sanitize_next($next);

if (checker_is_authenticated()) {
  header('location: ' . $next);
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $code = isset($_POST['code']) ? (string) $_POST['code'] : '';
  try {
    $ret = checker_authenticate_code($code);
    if (!($ret['ok'] ?? false)) {
      $error = (string) ($ret['message'] ?? '密码不正确');
    } else {
      checker_session_start();
      session_regenerate_id(true);
      $_SESSION['checker_code'] = (string) ($ret['code'] ?? '');
      header('location: ' . $next);
      exit;
    }
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="zh-CN">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>CiRCLE YUMMY PARTY - Checker Login</title>
  <link rel="icon" href="img/logo.webp" type="image/webp" />
  <link rel="stylesheet" href="./style.css" />
</head>

<body>
  <main class="app" role="main">
    <section class="glass" aria-label="Checker Login">
      <header class="top">
        <img class="logo" src="img/logo.webp" alt="CiRCLE YUMMY PARTY" />
        <h1 class="title">检票验证</h1>
        <p class="subtitle">请输入检票密码后进入验票页面</p>
      </header>

      <?php if (trim($error) !== ''): ?>
        <div class="alert alert-error" role="alert">

          <?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <form method="post" action="./login.php" class="form">
        <input type="hidden" name="next"
          value="<?php echo htmlspecialchars($next, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />

        <label class="field">
          <span class="k">检票密码</span>
          <input class="input" name="code" type="password" autocomplete="current-password" placeholder="" required />
        </label>

        <div class="controls">
          <button class="btn btn-small" type="submit">进入</button>
          <a class="btn btn-small btn-ghost" href="./login.php">清空</a>
        </div>
      </form>

      <footer class="foot">
        <div class="footRow">
          <span class="muted">未获取密码？请联系活动主办方</span>
        </div>
      </footer>
    </section>
  </main>
</body>

</html>