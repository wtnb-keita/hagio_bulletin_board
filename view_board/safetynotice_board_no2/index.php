<?php
$baseUrl = rtrim(str_replace('\\', '/', str_replace(rtrim($_SERVER['DOCUMENT_ROOT'],'/\\'), '', dirname(dirname(dirname(__FILE__))))), '/');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=1800">
<title>安全掲示板 No.2</title>
<link rel="stylesheet" href="../../assets/css/common.css">
<link rel="stylesheet" href="../../assets/css/view-board.css">
<style>
#slideshow { position:relative; width:100%; height:100%; }
.slide     { position:absolute; inset:0; }

#slide-nav {
  position: fixed;
  bottom: 14px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 10px;
  z-index: 9999;
  background: rgba(0,0,0,.35);
  padding: 6px 12px;
  border-radius: 20px;
}
.slide-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: rgba(255,255,255,.35);
  border: 2px solid rgba(255,255,255,.7);
  cursor: pointer;
  padding: 0;
  transition: background .25s, transform .2s;
}
.slide-dot.active { background: #fff; transform: scale(1.25); }
.slide-dot:hover  { background: rgba(255,255,255,.7); }
</style>
</head>
<body>
<div id="slideshow"></div>
<div id="clock"></div>
<div id="slide-nav" style="display:none"></div>

<script>const BASE_URL = '<?= $baseUrl ?>';</script>
<script src="../../assets/js/api.js"></script>
<script src="../../assets/js/panel-render.js"></script>
<script>
const BOARD_KEY = 'safety_board_2';

let allPages  = [];
let allPanels = [];
let boardCfg  = { width: 1800, height: 900, slideshow_enabled: false, slideshow_interval: 10 };
let curSlide  = 0;
let ssTimer   = null;

function applySize(w, h) {
  document.body.style.width  = w + 'px';
  document.body.style.height = h + 'px';
  document.querySelector('meta[name="viewport"]').content = `width=${w}`;
}

async function loadAll() {
  try {
    const [cfgRes, pagesRes, panelsRes] = await Promise.all([
      fetch(`${BASE_URL}/api/boards.php?board=${BOARD_KEY}`).then(r => r.json()),
      fetch(`${BASE_URL}/api/pages.php?board=${BOARD_KEY}`).then(r => r.json()),
      API.getPanels(BOARD_KEY),
    ]);
    boardCfg  = cfgRes;
    allPages  = pagesRes.pages  || [{ page_number: 1, page_name: 'ページ 1' }];
    allPanels = panelsRes.panels || [];
    applySize(boardCfg.width || 1800, boardCfg.height || 900);
  } catch(e) { console.error('データ取得失敗:', e); }
}

function renderSlides() {
  const ss = document.getElementById('slideshow');
  ss.innerHTML = '';
  allPages.forEach((pg, i) => {
    const slide = document.createElement('div');
    slide.className = 'slide';
    slide.id = 'slide-' + i;
    slide.style.display = i === curSlide ? '' : 'none';
    PanelRender.renderBoard(slide, allPanels.filter(p => (p.page || 1) === pg.page_number));
    ss.appendChild(slide);
  });
  renderDots();
}

function renderDots() {
  const nav = document.getElementById('slide-nav');
  if (allPages.length <= 1) { nav.style.display = 'none'; return; }
  nav.style.display = '';
  nav.innerHTML = allPages.map((pg, i) =>
    `<button class="slide-dot ${i === curSlide ? 'active' : ''}"
             onclick="goSlide(${i})" title="${PanelRender.escHtml(pg.page_name)}"></button>`
  ).join('');
}

function goSlide(idx) {
  const slides = document.querySelectorAll('.slide');
  if (!slides[idx]) return;
  slides[curSlide].style.display = 'none';
  curSlide = idx;
  slides[curSlide].style.display = '';
  renderDots();
  restartTimer();
}

function restartTimer() {
  if (ssTimer) clearInterval(ssTimer);
  if (boardCfg.slideshow_enabled && allPages.length > 1) {
    const ms = Math.max(3, boardCfg.slideshow_interval || 10) * 1000;
    ssTimer = setInterval(() => goSlide((curSlide + 1) % allPages.length), ms);
  }
}

function updateClock() {
  const now = new Date(), pad = n => String(n).padStart(2,'0');
  document.getElementById('clock').textContent =
    `${now.getFullYear()}/${pad(now.getMonth()+1)}/${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
}

let lastVersion    = 0;
let lastSsEnabled  = null;
let lastSsInterval = null;
async function checkVersion() {
  try {
    const [vRes, cfgRes] = await Promise.all([
      fetch(`${BASE_URL}/api/panels.php?board=${BOARD_KEY}&action=version`).then(r => r.json()),
      fetch(`${BASE_URL}/api/boards.php?board=${BOARD_KEY}`).then(r => r.json()),
    ]);
    const v = vRes.version || 0;
    const cfgChanged = lastSsEnabled !== null && (
      cfgRes.slideshow_enabled  !== lastSsEnabled ||
      cfgRes.slideshow_interval !== lastSsInterval
    );
    if ((lastVersion && v !== lastVersion) || cfgChanged) {
      await loadAll();
      renderSlides();
      restartTimer();
    }
    lastVersion    = v;
    lastSsEnabled  = cfgRes.slideshow_enabled;
    lastSsInterval = cfgRes.slideshow_interval;
  } catch(e) {}
}

(async () => {
  await loadAll();
  renderSlides();
  restartTimer();
  updateClock();
  setInterval(updateClock, 1000);
  setInterval(checkVersion, 5000);
})();
</script>
</body>
</html>
