const els = {
  startBtn: document.getElementById("startBtn"),
  photoBtn: document.getElementById("photoBtn"),
  photoInput: document.getElementById("photoInput"),
  resetAllBtn: document.getElementById("resetAllBtn"),
  stopBtn: document.getElementById("stopBtn"),
  video: document.getElementById("video"),
  name: document.getElementById("name"),
  email: document.getElementById("email"),
  no: document.getElementById("no"),
  verify: document.getElementById("verify"),
  entryState: document.getElementById("entryState"),
  entryStateText: document.getElementById("entryStateText"),
  verifiedMeta: document.getElementById("verifiedMeta"),
  checkedCount: document.getElementById("checkedCount"),
  raw: document.getElementById("raw"),
};

let stream = null;
let scanning = false;
let lastTicketNo = null;
let jsqrModulePromise = null;
let rafId = 0;
let scanCanvas = null;
let scanCtx = null;
let lastScanAt = 0;

const SCAN_INTERVAL_MS = 120;
const SCAN_MAX_WIDTH = 720;

function setStatus(text) {
  // Some layouts don't include a dedicated status node; fall back to meta line.
  if (els.status) {
    els.status.textContent = text;
    return;
  }
  if (els.verifiedMeta) {
    els.verifiedMeta.textContent = String(text ?? "");
  }
}

function setEntryState(kind, meta = "") {
  const map = {
    idle: { cls: "state-idle", text: "-" },
    scanning: { cls: "state-scanning", text: "扫描中" },
    verifying: { cls: "state-verifying", text: "核验中" },
    pass: { cls: "state-pass", text: "通过" },
    already: { cls: "state-already", text: "已入场" },
    fail: { cls: "state-fail", text: "失败" },
  };
  const cfg = map[kind] ?? map.idle;
  if (els.entryState) els.entryState.className = `stateCard ${cfg.cls}`;
  if (els.entryStateText) els.entryStateText.textContent = cfg.text;
  if (els.verifiedMeta) els.verifiedMeta.textContent = meta || "";
}

function setVerify(text, ok) {
  els.verify.textContent = text;
  els.verify.style.color = ok === true ? "var(--pass)" : ok === false ? "var(--fail)" : "inherit";
}

function confirmAction(message) {
  // eslint-disable-next-line no-alert
  return window.confirm(message);
}

async function refreshServerStats() {
  try {
    const resp = await fetch("./api/stats.php", { cache: "no-store" });
    const data = await resp.json().catch(() => ({}));
    if (resp.ok && data?.ok === true) {
      const n = data?.stats?.checked_count;
      if (typeof n === "number" && els.checkedCount) els.checkedCount.textContent = String(n);
    }
  } catch {
    // ignore
  }
}

async function resetAllChecked() {
  if (!confirmAction("确认：一键重置所有人入场状态为未入场？")) return;
  setEntryState("verifying");
  setStatus("重置中…");
  try {
    const resp = await fetch("./api/reset_all.php", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: "{}",
      cache: "no-store",
    });
    const data = await resp.json().catch(() => ({}));
    if (!resp.ok || data?.ok !== true) throw new Error(String(data?.message ?? `API error (${resp.status})`));
    const n = data?.stats?.checked_count;
    if (typeof n === "number" && els.checkedCount) els.checkedCount.textContent = String(n);
    else if (els.checkedCount) els.checkedCount.textContent = "0";
    setEntryState("idle");
    setStatus("已重置");
  } catch (e) {
    setEntryState("fail");
    setStatus(String(e?.message ?? e));
  }
}

function downloadText(text, filename, mime) {
  const blob = new Blob([text], { type: mime || "text/plain" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 2000);
}

async function uncheckTicket(no) {
  if (!confirmAction(`确认将 ${no} 改为未入场？`)) return;
  setEntryState("verifying");
  setStatus("修改中…");
  try {
    const resp = await fetch("./api/uncheck.php", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify({ no }),
      cache: "no-store",
    });
    const data = await resp.json().catch(() => ({}));
    if (!resp.ok || data?.ok !== true) throw new Error(String(data?.message ?? `API error (${resp.status})`));
    const n = data?.stats?.checked_count;
    if (typeof n === "number" && els.checkedCount) els.checkedCount.textContent = String(n);
    else refreshServerStats();
    setEntryState("idle");
    if (els.entryState) els.entryState.classList.remove("state-clickable");
    setStatus("已改为未入场");
  } catch (e) {
    setEntryState("fail");
    setStatus(String(e?.message ?? e));
  }
}

async function waitForVideoReady(video, timeoutMs = 3000) {
  if (video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0) return;
  await new Promise((resolve) => {
    let done = false;
    const finish = () => {
      if (done) return;
      done = true;
      resolve();
    };
    const onReady = () => finish();
    video.addEventListener("loadedmetadata", onReady, { once: true });
    video.addEventListener("loadeddata", onReady, { once: true });
    setTimeout(() => finish(), timeoutMs);
  });
}

async function loadJsQR() {
  if (jsqrModulePromise) return jsqrModulePromise;
  jsqrModulePromise = import("https://cdn.jsdelivr.net/npm/jsqr@1.4.0/+esm").then((m) => m?.default ?? m);
  return jsqrModulePromise;
}

async function decodeImageFile(jsQR, file) {
  if (!file) throw new Error("未选择图片");
  if (!file.type?.startsWith("image/")) throw new Error("请选择图片文件");

  const { canvas, ctx } = ensureScanCanvas();

  const drawToCanvas = async () => {
    if ("createImageBitmap" in window) {
      const bitmap = await createImageBitmap(file);
      const vw = bitmap.width || 0;
      const vh = bitmap.height || 0;
      if (vw <= 0 || vh <= 0) throw new Error("图片尺寸无效");
      const { w, h } = computeScaledSize(vw, vh);
      canvas.width = w;
      canvas.height = h;
      ctx.drawImage(bitmap, 0, 0, w, h);
      bitmap.close?.();
      return { w, h };
    }

    const url = URL.createObjectURL(file);
    try {
      const img = new Image();
      img.decoding = "async";
      await new Promise((resolve, reject) => {
        img.onload = () => resolve();
        img.onerror = () => reject(new Error("图片加载失败"));
        img.src = url;
      });
      const vw = img.naturalWidth || img.width || 0;
      const vh = img.naturalHeight || img.height || 0;
      if (vw <= 0 || vh <= 0) throw new Error("图片尺寸无效");
      const { w, h } = computeScaledSize(vw, vh);
      canvas.width = w;
      canvas.height = h;
      ctx.drawImage(img, 0, 0, w, h);
      return { w, h };
    } finally {
      URL.revokeObjectURL(url);
    }
  };

  const { w, h } = await drawToCanvas();
  const imageData = ctx.getImageData(0, 0, w, h);
  const code = jsQR(imageData.data, w, h, { inversionAttempts: "attemptBoth" });
  return code?.data || null;
}

function ensureScanCanvas() {
  if (!scanCanvas) scanCanvas = document.createElement("canvas");
  if (!scanCtx) {
    scanCtx = scanCanvas.getContext("2d", { willReadFrequently: true });
    if (!scanCtx) throw new Error("无法创建 Canvas（需要用于二维码识别）");
  }
  return { canvas: scanCanvas, ctx: scanCtx };
}

function computeScaledSize(vw, vh) {
  const targetW = Math.min(SCAN_MAX_WIDTH, vw);
  const scale = targetW / vw;
  const w = Math.max(1, Math.round(vw * scale));
  const h = Math.max(1, Math.round(vh * scale));
  return { w, h };
}

function scanOnce(jsQR) {
  const vw = els.video.videoWidth || 0;
  const vh = els.video.videoHeight || 0;
  if (vw <= 0 || vh <= 0) return null;

  const { canvas, ctx } = ensureScanCanvas();
  const { w, h } = computeScaledSize(vw, vh);
  canvas.width = w;
  canvas.height = h;

  ctx.drawImage(els.video, 0, 0, w, h);
  const imageData = ctx.getImageData(0, 0, w, h);
  const code = jsQR(imageData.data, w, h, { inversionAttempts: "dontInvert" });
  return code?.data || null;
}

async function handleDetected(rawValue) {
  scanning = false;
  await stop();
  els.raw.textContent = String(rawValue ?? "-");
  setEntryState("verifying");
  setStatus("已识别，正在验签…");
  try {
    // Always send raw QR content to server to parse/verify/update CSV.
    const resp = await fetch("./api/verify.php", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify({ raw: rawValue }),
      cache: "no-store",
    });
    const data = await resp.json().catch(() => ({}));
    if (!resp.ok || data?.ok !== true) throw new Error(String(data?.message ?? `API error (${resp.status})`));

    const no = String(data?.no ?? "-");
    lastTicketNo = no && no !== "-" ? no : null;
    els.no.textContent = no || "-";
    els.name.textContent = String(data?.name ?? "-") || "-";
    els.email.textContent = String(data?.email ?? "-") || "-";

    const statsN = data?.stats?.checked_count;
    if (typeof statsN === "number" && els.checkedCount) els.checkedCount.textContent = String(statsN);

    const status = String(data?.status ?? "");
    if (status === "pass") {
      setVerify("有效", true);
      setEntryState("pass", "已标记为入场");
      if (els.entryState) els.entryState.classList.remove("state-clickable");
      setStatus("完成");
      return;
    }
    if (status === "already") {
      setVerify("有效", true);
      setEntryState("already", "此票已入场（点击可改为未入场）");
      if (els.entryState) els.entryState.classList.add("state-clickable");
      setStatus("完成");
      return;
    }
    // fail
    setVerify(String(data?.message ?? "失败"), false);
    setEntryState("fail");
    if (els.entryState) els.entryState.classList.remove("state-clickable");
    setStatus("失败");
  } catch (e) {
    setVerify(String(e?.message ?? e), false);
    setEntryState("fail");
    setStatus("失败");
  }
}

function scanLoop(jsQR) {
  if (!scanning) return;
  rafId = requestAnimationFrame(() => scanLoop(jsQR));

  const now = performance.now();
  if (now - lastScanAt < SCAN_INTERVAL_MS) return;
  lastScanAt = now;

  try {
    const rawValue = scanOnce(jsQR);
    if (rawValue) {
      cancelAnimationFrame(rafId);
      rafId = 0;
      handleDetected(rawValue);
    }
  } catch (e) {
    const msg = String(e?.message ?? e);
    if (msg.includes("无法创建 Canvas")) {
      scanning = false;
      stop();
      setStatus(msg);
      return;
    }
    // ignore transient scan errors and keep looping
  }
}

async function start() {
  if (scanning) return;
  if (!navigator.mediaDevices?.getUserMedia) {
    setStatus("当前环境不支持 getUserMedia（无法实时调用相机），请用「拍照/选图识别」");
    setEntryState("idle");
    return;
  }

  setVerify("-", null);
  setEntryState("scanning");
  els.raw.textContent = "-";
  els.name.textContent = "-";
  els.email.textContent = "-";
  els.no.textContent = "-";

  setStatus("加载识别模块…");
  let jsQR;
  try {
    jsQR = await loadJsQR();
  } catch (e) {
    setStatus(
      `无法加载二维码识别模块（jsQR）：${e?.message ?? e}`
    );
    return;
  }

  setStatus("请求相机权限…");
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: "environment" },
      audio: false,
    });
  } catch (e) {
    setStatus(`无法打开相机：${e?.message ?? e}`);
    return;
  }

  els.video.srcObject = stream;
  await els.video.play().catch(() => { });
  await waitForVideoReady(els.video);
  try {
    ensureScanCanvas();
  } catch (e) {
    setStatus(String(e?.message ?? e));
    await stop();
    return;
  }

  scanning = true;
  lastScanAt = 0;
  els.startBtn.disabled = true;
  els.stopBtn.disabled = false;
  setStatus("扫描中…");
  scanLoop(jsQR);
}

async function stop() {
  scanning = false;
  if (rafId) {
    cancelAnimationFrame(rafId);
    rafId = 0;
  }
  els.startBtn.disabled = false;
  els.stopBtn.disabled = true;
  if (stream) {
    for (const track of stream.getTracks()) track.stop();
    stream = null;
  }
  els.video.srcObject = null;
}

async function pickPhotoAndVerify() {
  if (scanning) return;
  if (!els.photoInput) {
    setStatus("页面缺少 photoInput");
    return;
  }

  setVerify("-", null);
  setEntryState("verifying");
  els.raw.textContent = "-";
  els.name.textContent = "-";
  els.email.textContent = "-";
  els.no.textContent = "-";

  setStatus("加载识别模块…");
  let jsQR;
  try {
    jsQR = await loadJsQR();
  } catch (e) {
    setStatus(
      `无法加载二维码识别模块（jsQR）：${e?.message ?? e}`
    );
    return;
  }

  // Must be triggered by user gesture on iOS; this handler is bound to a click.
  els.photoInput.value = "";
  els.photoInput.click();

  const file = await new Promise((resolve) => {
    const onChange = () => resolve(els.photoInput.files?.[0] ?? null);
    els.photoInput.addEventListener("change", onChange, { once: true });
  });

  if (!file) {
    setStatus("未选择图片");
    return;
  }

  setStatus("识别图片中…");
  try {
    ensureScanCanvas();
    const rawValue = await decodeImageFile(jsQR, file);
    if (!rawValue) throw new Error("未识别到二维码（请对准、保证清晰度）");
    await handleDetected(rawValue);
  } catch (e) {
    setVerify(String(e?.message ?? e), false);
    setEntryState("fail");
    setStatus("失败");
  }
}

setEntryState("idle");
if (els.checkedCount) els.checkedCount.textContent = "0";

els.startBtn.addEventListener("click", start);
els.stopBtn.addEventListener("click", stop);
els.photoBtn?.addEventListener("click", pickPhotoAndVerify);
els.resetAllBtn?.addEventListener("click", resetAllChecked);
els.entryState?.addEventListener("click", () => {
  const isAlready = els.entryState?.classList?.contains("state-already");
  if (!isAlready) return;
  if (!lastTicketNo) return;
  uncheckTicket(lastTicketNo);
});

refreshServerStats();
