<?php
$baseUrl = rtrim(str_replace('\\', '/', str_replace(rtrim($_SERVER['DOCUMENT_ROOT'],'/\\'), '', dirname(dirname(dirname(__FILE__))))), '/');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>安全資格者掲示板</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

/* 画面全体を黒背景で埋め、ボードを中央にスケール表示 */
body {
  background: #111;
  width: 100vw;
  height: 100vh;
  overflow: hidden;
  font-family: 'Meiryo', 'Yu Gothic', sans-serif;
}

#board {
  position: fixed;
  top: 50%; left: 50%;
  margin: 0;
  background: #e8edf3;
  transform-origin: center;
  overflow: hidden;
}

/* ===== ヘッダー ===== */
#header {
  height: 52px;
  background: #1a4f72;
  border-bottom: 4px solid #e94560;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 24px;
  gap: 10px;
}
#header .cross {
  position: relative;
  width: 28px; height: 28px;
  flex-shrink: 0;
}
#header .cross::before,
#header .cross::after {
  content: '';
  position: absolute;
  background: #2ecc40;
  border-radius: 2px;
}
#header .cross::before { width: 34%; height: 100%; left: 33%; top: 0; }
#header .cross::after  { width: 100%; height: 34%; left: 0; top: 33%; }
#header h1     { font-size: 24px; font-weight: bold; color: #fff; letter-spacing: .06em; }
#header #clock { margin-left: auto; font-size: 13px; color: rgba(255,255,255,.7); }

/* ===== スライド ===== */
#slideshow { position: relative; }
.staff-slide { display: none; }
.staff-slide.active { display: grid; }

/* ===== カード ===== */
.card {
  background: #fff;
  border: 3px solid #f5c518;
  border-radius: 12px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 1px 4px rgba(0,0,0,.08);
}
.card-empty {
  background: rgba(255,255,255,.35);
  border: 1px dashed #b8cfe0;
  border-radius: 12px;
}

/* 上部：黄色枠 */
.card-yellow-corner {
  flex-shrink: 0;
}
.card-top {
  display: flex;
  gap: 10px;
  padding: 12px;
  border-bottom: 1px solid #eee;
  background: #fffde7;
  min-height: 130px;
}

/* 写真 */
.card-photo {
  width: 100px;
  height: 100px;
  flex-shrink: 0;
  border-radius: 8px;
  overflow: hidden;
  background: #d0e0ef;
}
.card-photo img {
  width: 100%; height: 100%;
  object-fit: cover; display: block;
}
.card-photo-none {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  font-size: 36px; color: #8aaec8;
}

/* 右側情報 */
.card-top-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-width: 0;
}
.badge-anzen {
  align-self: center;
  display: flex;
  align-items: center;
  gap: 8px;
  color: #2e7d32;
  font-size: 20px;
  font-weight: bold;
  white-space: nowrap;
}
.safety-cross { position: relative; width: 32px; height: 32px; flex-shrink: 0; }
.safety-cross::before,
.safety-cross::after {
  content: ''; position: absolute;
  background: #2ecc40; border-radius: 2px;
}
.safety-cross::before { width: 34%; height: 100%; left: 33%; top: 0; }
.safety-cross::after  { width: 100%; height: 34%; left: 0; top: 33%; }

/* カード下部 */
.card-body {
  flex: 1;
  display: flex; flex-direction: column;
  padding: 8px 10px 6px;
  min-height: 0;
}
.card-basic-info {
  display: flex;
  flex-direction: column;
  margin-bottom: 6px;
  flex-shrink: 0;
}
.card-basic-info > div {
  display: flex;
  gap: 20px;
}
.card-basic-info > div:first-child {
  margin-bottom: 4px;
}
.card-basic-info > div:first-child .card-info-label {
  flex: 1;
}
.card-basic-info > div:first-child .card-info-label:last-child {
  flex: 0 0 auto;
}
.card-basic-info > div:last-child .card-name {
  flex: 1;
}
.card-basic-info > div:last-child .card-blood {
  flex: 0 0 auto;
}
.card-info-label { font-size: 10px; color: #666; margin-bottom: 1px; }
.card-name {
  font-size: 18px; font-weight: 700; color: #1a2e40;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.card-name-row { display: flex; align-items: baseline; gap: 10px; }
.card-name-row .card-name { flex: 1; }
.card-blood    { font-size: 16px; font-weight: 700; color: #c62828; margin-left: auto; white-space: nowrap; }
.card-job-type { margin-top: 16px; font-size: 16px; font-weight: 600; color: #1a4f72; background: #dceeff; border-radius: 4px; padding: 4px 12px; white-space: nowrap; }
.card-qual-label { font-size: 13px; font-weight: 700; margin-bottom: 6px; flex-shrink: 0; }
.card-quals {
  flex: 1; display: flex; flex-wrap: wrap;
  align-content: flex-start; gap: 6px; overflow: hidden;
}
.qual {
  padding: 4px 10px;
  border-radius: 20px;
  background: #eef6ff;
  border: 1px solid #c5daf7;
  color: #1a4f72;
  font-size: 13px;
  white-space: nowrap; line-height: 1.5;
}

/* ===== ページナビ ===== */
#slide-nav {
  display: none;
  position: absolute;
  bottom: 14px; left: 50%;
  transform: translateX(-50%);
  gap: 10px;
  z-index: 9999;
  background: rgba(0,0,0,.35);
  padding: 6px 12px;
  border-radius: 20px;
}
.slide-dot {
  width: 12px; height: 12px; border-radius: 50%;
  background: rgba(255,255,255,.35);
  border: 2px solid rgba(255,255,255,.7);
  cursor: pointer; padding: 0;
  transition: background .25s, transform .2s;
}
.slide-dot.active { background: #fff; transform: scale(1.25); }
.slide-dot:hover  { background: rgba(255,255,255,.7); }
</style>
</head>
<body>

<div id="board">
  <div id="header">
    <span class="cross"></span>
    <h1 id="title">安全資格者掲示板</h1>
    <div id="clock"></div>
  </div>
  <div id="slideshow"></div>
  <div id="slide-nav"></div>
</div>

<script>
const BASE_URL  = '<?= $baseUrl ?>';
const BOARD_KEY = 'staff_board';

/* カードの基準サイズ（1800×900・5列2行時の寸法。縦横比はこれで固定） */
const BASE_CW = 346;
const BASE_CH = 409;

let COLS     = 5;
let ROWS     = 2;
let PER_PAGE = COLS * ROWS;

let _pages    = [];
let _staffCache = [];
let _curSlide = 0;
let _ssTimer  = null;
let _boardCfg = { slideshow_enabled: false, slideshow_interval: 10 };
let _boardW   = 1800;
let _boardH   = 900;

/* ===== スケール適用 ===== */
function applyScale() {
  const scale = Math.min(window.innerWidth / _boardW, window.innerHeight / _boardH);
  const board = document.getElementById('board');
  board.style.width     = _boardW + 'px';
  board.style.height    = _boardH + 'px';
  board.style.transform = `translate(-50%, -50%) scale(${scale})`;
}

/* ===== HTML エスケープ ===== */
function eh(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ===== カード生成 ===== */
function buildCard(s) {
  const photoHtml = s.photoPath
    ? `<img src="${eh(s.photoPath)}" alt="${eh(s.name)}">`
    : `<div class="card-photo-none">👤</div>`;
  const quals = (s.qualifications || []).map(q => `<span class="qual">${eh(q)}</span>`).join('');
  const qualSection = quals
    ? `<div class="card-qual-label">資格</div><div class="card-quals">${quals}</div>`
    : `<div class="card-quals"></div>`;
  const card = document.createElement('div');
  card.className = 'card';
  card.innerHTML = `
    <div class="card-yellow-corner">
      <div class="card-top">
        <div class="card-photo">${photoHtml}</div>
        <div class="card-top-info">
          <div class="badge-anzen"><div class="safety-cross"></div>安全第一</div>
          ${s.jobType ? `<div class="card-job-type">職種：${eh(s.jobType)}</div>` : ''}
        </div>
      </div>
    </div>
    <div class="card-body">
      <div class="card-basic-info">
        <div>
          <div class="card-info-label">名前</div>
          ${s.department ? `<div class="card-info-label">血液型</div>` : ''}
        </div>
        <div>
          <div class="card-name">${eh(s.name)}</div>
          ${s.department ? `<div class="card-blood">${eh(s.department)}</div>` : ''}
        </div>
      </div>
      ${quals ? `<div class="card-qual-label">保有資格</div><div class="card-quals">${quals}</div>` : ''}
    </div>`;
  return card;
}

/* ===== スライド描画 ===== */
function renderSlides(list) {
  _staffCache = list;
  const pageCount = Math.max(1, Math.ceil(list.length / PER_PAGE));
  if (_curSlide >= pageCount) _curSlide = 0;

  _pages = [];
  for (let i = 0; i < list.length; i += PER_PAGE) {
    _pages.push(list.slice(i, i + PER_PAGE));
  }
  if (_pages.length === 0) _pages.push([]);

  const ss     = document.getElementById('slideshow');
  const slideH = _boardH - 52;
  ss.innerHTML = '';
  ss.style.height = slideH + 'px';

  // 利用可能領域から、基準縦横比(BASE_CW:BASE_CH)を保ったままカードサイズを算出
  const gap    = 10;
  const availW = _boardW - 28 - gap * (COLS - 1);
  const availH = slideH  - 20 - gap * (ROWS - 1);
  const scale  = Math.min(availW / COLS / BASE_CW, availH / ROWS / BASE_CH);
  const cardW  = BASE_CW * scale;
  const cardH  = BASE_CH * scale;

  _pages.forEach((pageStaff, pi) => {
    const slide = document.createElement('div');
    slide.className = `staff-slide${pi === _curSlide ? ' active' : ''}`;
    slide.style.cssText = `
      height:${slideH}px;
      grid-template-columns:repeat(${COLS},${cardW}px);
      grid-template-rows:repeat(${ROWS},${cardH}px);
      gap:${gap}px;
      padding:10px 14px;
      justify-content:center;
      align-content:center;
    `;
    for (let i = 0; i < PER_PAGE; i++) {
      const cell = document.createElement('div');
      cell.style.cssText = `width:${cardW}px;height:${cardH}px;position:relative;`;
      if (pageStaff[i]) {
        const card = buildCard(pageStaff[i]);
        card.style.cssText = `width:${BASE_CW}px;height:${BASE_CH}px;transform:scale(${scale});transform-origin:top left;`;
        cell.appendChild(card);
      } else {
        cell.className = 'card-empty';
      }
      slide.appendChild(cell);
    }
    ss.appendChild(slide);
  });

  renderDots();
  restartTimer();
}

function renderDots() {
  const nav = document.getElementById('slide-nav');
  if (_pages.length <= 1) { nav.style.display = 'none'; return; }
  nav.style.display = 'flex';
  nav.innerHTML = _pages.map((_, i) =>
    `<button class="slide-dot${i === _curSlide ? ' active' : ''}" onclick="goSlide(${i})"></button>`
  ).join('');
}

function goSlide(idx) {
  const slides = document.querySelectorAll('.staff-slide');
  if (!slides[idx]) return;
  slides[_curSlide].classList.remove('active');
  _curSlide = idx;
  slides[_curSlide].classList.add('active');
  renderDots();
  restartTimer();
}

function restartTimer() {
  if (_ssTimer) clearInterval(_ssTimer);
  if (_boardCfg.slideshow_enabled && _pages.length > 1) {
    const ms = Math.max(3, _boardCfg.slideshow_interval || 10) * 1000;
    _ssTimer = setInterval(() => goSlide((_curSlide + 1) % _pages.length), ms);
  }
}

/* ===== データ取得 ===== */
async function refresh() {
  try {
    const r    = await fetch(`${BASE_URL}/api/staff.php?board=${BOARD_KEY}`);
    const json = await r.json();
    renderSlides(json.staff || []);
  } catch(e) { console.error(e); }
}

/* ===== ボード設定 ===== */
async function loadConfig() {
  try {
    const r   = await fetch(`${BASE_URL}/api/boards.php?board=${BOARD_KEY}`);
    const cfg = await r.json();
    _boardCfg = cfg;
    _boardW   = cfg.width  || 1800;
    _boardH   = cfg.height || 900;
    COLS      = Math.max(1, parseInt(cfg.grid_cols) || 5);
    ROWS      = Math.max(1, parseInt(cfg.grid_rows) || 2);
    PER_PAGE  = COLS * ROWS;
    if (cfg.name) document.getElementById('title').textContent = cfg.name;
    applyScale();
  } catch(e) {}
}

/* 設定変更（レイアウト等）の監視 */
async function checkConfigUpdate() {
  const prev = `${_boardW}x${_boardH}:${COLS}x${ROWS}:${_boardCfg.slideshow_enabled}:${_boardCfg.slideshow_interval}`;
  await loadConfig();
  const now  = `${_boardW}x${_boardH}:${COLS}x${ROWS}:${_boardCfg.slideshow_enabled}:${_boardCfg.slideshow_interval}`;
  if (prev !== now) renderSlides(_staffCache);
}

/* ===== 時計 ===== */
function tick() {
  const n = new Date(), p = x => String(x).padStart(2,'0');
  document.getElementById('clock').textContent =
    `${n.getFullYear()}/${p(n.getMonth()+1)}/${p(n.getDate())} ${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`;
}

window.addEventListener('resize', applyScale);

(async () => {
  await loadConfig();
  await refresh();
  tick();
  setInterval(tick, 1000);

  let _lastTs = '';
  async function checkUpdate() {
    try {
      const r   = await fetch(`${BASE_URL}/api/staff.php?board=${BOARD_KEY}&check=1`);
      const d   = await r.json();
      if (d.ts && d.ts !== _lastTs) {
        if (_lastTs !== '') await refresh();
        _lastTs = d.ts;
      }
    } catch(e) {}
  }
  await checkUpdate();
  setInterval(checkUpdate, 3000);
  setInterval(checkConfigUpdate, 5000);
})();
</script>
</body>
</html>
