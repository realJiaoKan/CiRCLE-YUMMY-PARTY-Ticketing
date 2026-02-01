<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="zh-CN">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>CiRCLE YUMMY PARTY - Ticket Checker</title>
  <link rel="stylesheet" href="./style.css" />
</head>

<body>
  <main class="app" role="main">
    <section class="glass" aria-label="Ticket Checker">
      <header class="top">
        <img class="logo" src="img/logo.webp" alt="CiRCLE YUMMY PARTY" />
        <h1 class="title">入场验票</h1>
        <p class="subtitle">扫码或上传二维码图片，验票并标记入场状态</p>
      </header>

      <div class="controls">
        <button id="startBtn" class="btn btn-primary" type="button">扫描</button>
        <button id="photoBtn" class="btn" type="button">上传</button>
        <button id="stopBtn" class="btn btn-ghost" type="button" disabled>停止</button>
      </div>
      <input id="photoInput" type="file" accept="image/*" capture="environment" hidden />

      <div class="stage">
        <div class="videoWrap">
          <video id="video" autoplay playsinline muted></video>
        </div>

        <aside class="side">
          <div id="entryState" class="stateCard state-idle" aria-live="polite">
            <div class="stateLabel">核验状态</div>
            <div id="entryStateText" class="stateValue">-</div>
            <div id="verifiedMeta" class="stateMeta"></div>
          </div>
        </aside>
      </div>

      <div class="info">
        <div class="infoRow">
          <div class="k">姓名</div>
          <div id="name" class="v">-</div>
        </div>
        <div class="infoRow">
          <div class="k">邮箱</div>
          <div id="email" class="v">-</div>
        </div>
        <div class="infoRow">
          <div class="k">番号</div>
          <div id="no" class="v">-</div>
        </div>
        <div class="infoRow">
          <div class="k">验签</div>
          <div id="verify" class="v">-</div>
        </div>
      </div>

      <details class="raw">
        <summary>原始二维码内容</summary>
        <pre id="raw">-</pre>
      </details>

      <footer class="foot">
        <div class="footRow">
          <button id="resetAllBtn" class="btn btn-small btn-ghost" type="button">一键重置全部未入场</button>
          <span class="muted">已入场：<span id="checkedCount">0</span></span>
        </div>
      </footer>
    </section>
  </main>

  <script src="./checker.js" type="module"></script>
</body>

</html>