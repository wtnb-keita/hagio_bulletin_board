<?php
$baseUrl = rtrim(str_replace('\\', '/', str_replace(rtrim($_SERVER['DOCUMENT_ROOT'],'/\\'), '', dirname(dirname(dirname(__FILE__))))), '/');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=1800">
<title>安全資格者掲示板</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  background: #e8edf3;
  width: 1800px;
  height: 900px;
  overflow: hidden;
  font-family: 'Meiryo', 'Yu Gothic', sans-serif;
}

/* ===== ヘッダー ===== */
#header {
  height: 52px;
  background: #1a4f72;
  border-bottom: 4px solid #e94560;
  display: flex;
  align-items: center;
  padding: 0 24px;
  gap: 10px;
}
#header .cross {
  position: relative;
  width: 28px;
  height: 28px;
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
#header h1       { font-size: 24px; font-weight: bold; color: #fff; letter-spacing: .06em; }
#header #clock   { margin-left: auto; font-size: 13px; color: rgba(255,255,255,.7); }

/* ===== グリッド ===== */
#grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  grid-template-rows: repeat(3, 1fr);
  gap: 10px;
  padding: 10px 14px;
  height: calc(900px - 52px);
}

/* ===== カード ===== */
.card {
  background: #fff;
  border: 1px solid #b8cfe0;
  border-radius: 8px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 1px 4px rgba(0,0,0,.08);
}
.card-empty {
  background: rgba(255,255,255,.35);
  border: 1px dashed #b8cfe0;
  border-radius: 8px;
}

/* --- 上段：写真エリア（カード高さの50%） --- */
.card-top {
  flex: 0 0 50%;
  display: flex;
  min-height: 0;
}

/* 大きい写真（左 60%） */
.card-photo {
  flex: 0 0 62%;
  overflow: hidden;
  background: #d0e0ef;
}
.card-photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.card-photo-none {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 44px;
  color: #8aaec8;
}

/* 右サイド（右 38%） */
.card-side {
  flex: 1;
  background: #f2f7fc;
  border-left: 1px solid #b8cfe0;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 4px 6px;
  gap: 8px;
}
.badge-anzen {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
}
.safety-cross {
  position: relative;
  width: 32px;
  height: 32px;
  flex-shrink: 0;
}
.safety-cross::before,
.safety-cross::after {
  content: '';
  position: absolute;
  background: #2ecc40;
  border-radius: 2px;
}
.safety-cross::before {
  width: 34%;
  height: 100%;
  left: 33%;
  top: 0;
}
.safety-cross::after {
  width: 100%;
  height: 34%;
  left: 0;
  top: 33%;
}
.badge-anzen-text {
  font-size: 9px;
  font-weight: bold;
  color: #1a4f72;
  letter-spacing: .03em;
  white-space: nowrap;
}
.card-avatar {
  width: 46px;
  height: 46px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid #fff;
  background: #c0d4e5;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  color: #7a9ab5;
  flex-shrink: 0;
}
.card-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.card-blood {
  font-size: 13px;
  font-weight: bold;
  color: #1a4f72;
  text-align: center;
  line-height: 1.2;
}
.card-blood-label {
  font-size: 9px;
  color: #7a8d9e;
  text-align: center;
}

/* --- 下段：情報エリア（残り50%） --- */
.card-bottom {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 7px 9px 6px;
  min-height: 0;
}

/* 名前・部署 行 */
.card-name-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
  margin-bottom: 5px;
  flex-shrink: 0;
}
.card-name {
  font-size: 19px;
  font-weight: bold;
  color: #1a2e40;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  min-width: 0;
}
.card-dept {
  font-size: 10px;
  color: #7a8d9e;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex-shrink: 0;
  max-width: 46%;
}

/* 資格エリア（固定・overflow hidden） */
.card-qual-label {
  font-size: 10px;
  color: #7a8d9e;
  margin-bottom: 4px;
  flex-shrink: 0;
}
.card-quals {
  flex: 1;
  display: flex;
  flex-wrap: wrap;
  align-content: flex-start;
  gap: 3px;
  overflow: hidden;
}
.qual {
  background: #fde8ec;
  border: 1px solid #f2a0b0;
  color: #b52840;
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 3px;
  white-space: nowrap;
  line-height: 1.4;
}
</style>
</head>
<body>

<div id="header">
  <span class="cross"></span>
  <h1 id="title">安全資格者掲示板</h1>
  <div id="clock"></div>
</div>

<div id="slideshow"></div>

<!-- ページナビ -->
<div id="slide-nav" style="
  display:none;
  position:fixed;
  bottom:14px;
  left:50%;
  transform:translateX(-50%);
  gap:10px;
  z-index:9999;
  background:rgba(0,0,0,.35);
  padding:6px 12px;
  border-radius:20px;
">
</div>
<style>
#slideshow { position:relative; width:100%; }
.staff-slide { display:none; }
.staff-slide.active { display:grid; }
.slide-dot {
  width:12px; height:12px; border-radius:50%;
  background:rgba(255,255,255,.35);
  border:2px solid rgba(255,255,255,.7);
  cursor:pointer; padding:0;
  transition:background .25s, transform .2s;
}
.slide-dot.active { background:#fff; transform:scale(1.25); }
.slide-dot:hover  { background:rgba(255,255,255,.7); }
</style>

<script>
const BASE_URL  = '<?= $baseUrl ?>';
const BOARD_KEY = 'staff_board';

/* ===== ボード設定 ===== */
async function loadConfig() {
  try {
    const r   = await fetch(`${BASE_URL}/api/boards.php?board=${BOARD_KEY}`);
    const cfg = await r.json();
    if (cfg.name) document.getElementById('title').textContent = cfg.name;
    const w = cfg.width  || 1800;
    const h = cfg.height || 900;
    document.body.style.cssText += `;width:${w}px;height:${h}px`;
    document.querySelector('meta[name=viewport]').content = `width=${w}`;
    document.getElementById('grid').style.height = (h - 52) + 'px';
  } catch(e) {}
}

/* ===== HTML エスケープ ===== */
function eh(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ===== カードHTML生成 ===== */
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
    <div class="card-top">
      <div class="card-photo">${photoHtml}</div>
      <div class="card-side">
        <div class="badge-anzen">
          <div class="safety-cross"></div>
          <div class="badge-anzen-text">安全第一</div>
        </div>
        ${s.department ? `<div class="card-blood-label">血液型</div><div class="card-blood">${eh(s.department)}</div>` : ''}
      </div>
    </div>
    <div class="card-bottom">
      <div class="card-name-row"><div class="card-name">${eh(s.name)}</div></div>
      ${qualSection}
    </div>`;
  return card;
}

/* ===== スライドショー状態 ===== */
let _pages   = [];
let _curSlide = 0;
let _ssTimer  = null;
let _boardCfg = { slideshow_enabled: false, slideshow_interval: 10 };

/* ===== スライド描画 ===== */
function renderSlides(list) {
  const COLS = 4, ROWS = 3, PER_PAGE = COLS * ROWS;
  _pages = [];
  for (let i = 0; i < list.length; i += PER_PAGE) {
    _pages.push(list.slice(i, i + PER_PAGE));
  }
  if (_pages.length === 0) _pages.push([]);

  const ss = document.getElementById('slideshow');
  ss.innerHTML = '';

  _pages.forEach((pageStaff, pi) => {
    const slide = document.createElement('div');
    slide.className = `staff-slide${pi === _curSlide ? ' active' : ''}`;
    slide.style.cssText = `
      grid-template-columns:repeat(${COLS},1fr);
      grid-template-rows:repeat(${ROWS},1fr);
      gap:10px;
      padding:10px 14px;
    `;
    for (let i = 0; i < PER_PAGE; i++) {
      if (pageStaff[i]) {
        slide.appendChild(buildCard(pageStaff[i]));
      } else {
        const empty = document.createElement('div');
        empty.className = 'card card-empty';
        slide.appendChild(empty);
      }
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

/* ===== ボード設定（サイズ + スライドショー） ===== */
async function loadConfig() {
  try {
    const r   = await fetch(`${BASE_URL}/api/boards.php?board=${BOARD_KEY}`);
    const cfg = await r.json();
    _boardCfg = cfg;
    if (cfg.name) document.getElementById('title').textContent = cfg.name;
    const w = cfg.width  || 1800;
    const h = cfg.height || 900;
    document.body.style.cssText += `;width:${w}px;height:${h}px`;
    document.querySelector('meta[name=viewport]').content = `width=${w}`;
    document.getElementById('slideshow').style.height = (h - 52) + 'px';
  } catch(e) {}
}

/* ===== 時計 ===== */
function tick() {
  const n = new Date(), p = x => String(x).padStart(2,'0');
  document.getElementById('clock').textContent =
    `${n.getFullYear()}/${p(n.getMonth()+1)}/${p(n.getDate())} ${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`;
}

(async () => {
  await loadConfig();
  await refresh();
  tick();
  setInterval(tick, 1000);
  setInterval(refresh, 60000);
})();
</script>
</body>
</html>
