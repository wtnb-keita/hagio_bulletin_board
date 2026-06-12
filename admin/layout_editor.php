<?php
require_once __DIR__ . '/../api/db.php';

$boardKey = $_GET['board'] ?? 'safety_board_1';

$BOARD_CFGS = [
    'safety_board_1' => [
        'title' => '安全掲示板 No.1',
        'back'  => 'safetynotice_board_no1/',
        'view'  => '/view_board/safetynotice_board_no1/index.php',
    ],
    'safety_board_2' => [
        'title' => '安全掲示板 No.2',
        'back'  => 'safetynotice_board_no2/',
        'view'  => '/view_board/safetynotice_board_no2/index.php',
    ],
];
if (!array_key_exists($boardKey, $BOARD_CFGS)) $boardKey = 'safety_board_1';
$cfg = $BOARD_CFGS[$boardKey];

$panels  = [];
$pages   = [];
$dbError = '';
try {
    $panels = fetchPanels($boardKey);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
$pages = fetchPages($boardKey);

$boardW = 1800;
$boardH = 900;
try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT width, height FROM boards WHERE board_key = ?');
    $stmt->execute([$boardKey]);
    $row  = $stmt->fetch();
    if ($row) { $boardW = (int)$row['width']; $boardH = (int)$row['height']; }
} catch (Throwable $e) {}

$panelsJson = json_encode($panels, JSON_UNESCAPED_UNICODE);
$pagesJson  = json_encode($pages,  JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>レイアウト編集 - <?= htmlspecialchars($cfg['title']) ?></title>
<link rel="stylesheet" href="../assets/css/common.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
body { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

.layout-header {
  background: var(--accent);
  color: #fff;
  padding: 8px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
  border-bottom: 2px solid #1b5e20;
}
.layout-header h1 { font-size: 16px; }
.layout-header .sep { color: rgba(255,255,255,0.4); }
.layout-header .btn-secondary { background:rgba(255,255,255,.2); color:#fff; border-color:rgba(255,255,255,.4); }
.layout-header .btn-secondary:hover { background:rgba(255,255,255,.35); }
.layout-header .btn-success  { background:#1b5e20; }
.layout-header .btn-accent2  { background:var(--accent2); color:#fff; border:none; }

/* ---- ページタブ ---- */
.layout-page-tabs {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
  padding: 6px 14px;
  background: var(--surface2);
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
  font-size: 12px;
}
.layout-page-tabs .tab-label { color: var(--text-dim); margin-right: 4px; }
.lp-tab {
  padding: 3px 12px;
  border-radius: 4px 4px 0 0;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-dim);
  font-size: 12px;
  cursor: pointer;
  font-family: inherit;
  transition: background .15s;
}
.lp-tab:hover  { background: var(--bg); }
.lp-tab.active { background: var(--bg); color: var(--text); border-color: var(--accent); font-weight: bold; }

.layout-toolbar {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 6px 14px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
  font-size: 12px;
  color: var(--text-dim);
}
.layout-toolbar .coord-display {
  margin-left: auto;
  font-size: 12px;
  color: var(--text-dim);
  min-width: 260px;
  text-align: right;
}
.layout-toolbar .coord-display b { color: var(--text); }

.layout-canvas-wrap {
  flex: 1;
  overflow: auto;
  background: #888;
  display: flex;
  align-items: flex-start;
  justify-content: flex-start;
  padding: 20px;
}

.layout-board {
  position: relative;
  width: <?= $boardW ?>px;
  height: <?= $boardH ?>px;
  background: #0f3460;
  flex-shrink: 0;
  transform-origin: top left;
  box-shadow: 0 4px 24px rgba(0,0,0,0.5);
}
.board-label {
  position: absolute;
  top: 4px; right: 8px;
  color: rgba(255,255,255,0.2);
  font-size: 11px;
  pointer-events: none;
}

/* ---- パネル ---- */
.le-panel {
  position: absolute;
  border: 2px solid rgba(255,255,255,0.3);
  border-radius: 4px;
  overflow: hidden;
  cursor: move;
  user-select: none;
  display: flex;
  flex-direction: column;
  background: rgba(255,255,255,0.06);
  transition: border-color .1s, box-shadow .1s;
}
.le-panel:hover  { border-color: rgba(255,255,255,0.6); }
.le-panel.active {
  border-color: #fff;
  box-shadow: 0 0 0 2px var(--accent), 0 0 16px rgba(46,125,50,0.6);
  z-index: 100 !important;
}
.le-panel.type-media       { border-color: rgba(165,214,167,0.5); }
.le-panel.type-text        { border-color: rgba(144,202,249,0.5); }
.le-panel.type-accident    { border-color: rgba(255,204,128,0.5); }
.le-panel.type-notice      { border-color: rgba(128,222,234,0.5); }
.le-panel.type-responsible { border-color: rgba(255,213,0,0.7);   }
.le-panel.type-disaster    { border-color: rgba(244,67,54,0.7);   }

.le-panel-title {
  font-size: 11px;
  font-weight: bold;
  padding: 3px 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex-shrink: 0;
  color: #fff;
  display: flex;
  align-items: center;
  gap: 4px;
}
.le-panel.type-media       .le-panel-title { background: rgba(46,125,50,0.85); }
.le-panel.type-text        .le-panel-title { background: rgba(2,119,189,0.85); }
.le-panel.type-accident    .le-panel-title { background: rgba(239,108,0,0.85); }
.le-panel.type-notice      .le-panel-title { background: rgba(0,131,143,0.85); }
.le-panel.type-responsible .le-panel-title { background: rgba(180,140,0,0.85); }
.le-panel.type-disaster    .le-panel-title {
  background: repeating-linear-gradient(45deg,#f44336 0,#f44336 8px,#111 8px,#111 16px);
}
.le-panel.no-title .le-panel-title { display: none; }

.le-panel-body {
  flex: 1;
  overflow: hidden;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}
.le-panel-body img { width:100%; height:100%; object-fit:contain; display:block; }
.le-panel-body .placeholder {
  color: rgba(255,255,255,0.3);
  font-size: 11px;
  text-align: center;
  padding: 4px;
  pointer-events: none;
}
.le-panel-body .accident-num {
  color: #ffcc80;
  font-size: clamp(10px,3cqw,40px);
  font-weight: bold;
  text-align: center;
  pointer-events: none;
}

/* リサイズハンドル */
.le-resize {
  position: absolute;
  width: 12px; height: 12px;
  background: #fff;
  border: 1px solid var(--accent);
  border-radius: 2px;
  z-index: 10;
  opacity: 0;
  transition: opacity .1s;
}
.le-panel:hover .le-resize,
.le-panel.active .le-resize { opacity: 1; }
.le-resize.nw { top:-6px;  left:-6px;  cursor:nw-resize; }
.le-resize.n  { top:-6px;  left:50%;   transform:translateX(-50%); cursor:n-resize; }
.le-resize.ne { top:-6px;  right:-6px; cursor:ne-resize; }
.le-resize.e  { top:50%;   right:-6px; transform:translateY(-50%); cursor:e-resize; }
.le-resize.se { bottom:-6px; right:-6px; cursor:se-resize; }
.le-resize.s  { bottom:-6px; left:50%;  transform:translateX(-50%); cursor:s-resize; }
.le-resize.sw { bottom:-6px; left:-6px; cursor:sw-resize; }
.le-resize.w  { top:50%;   left:-6px;  transform:translateY(-50%); cursor:w-resize; }

/* ---- 右サイド ---- */
.layout-side {
  width: 220px;
  flex-shrink: 0;
  background: var(--surface);
  border-left: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  padding: 12px;
  gap: 10px;
}
.layout-side h3 {
  font-size: 12px;
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}
.side-panel-list { display:flex; flex-direction:column; gap:4px; }
.side-panel-item {
  padding: 6px 8px;
  border-radius: 4px;
  border: 1px solid var(--border);
  font-size: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: background .1s;
}
.side-panel-item:hover  { background: var(--surface2); }
.side-panel-item.active { background: #e8f5e9; border-color: var(--accent); }
.side-panel-item .name  { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

.info-card { background:var(--surface2); border-radius:6px; padding:10px; font-size:12px; }
.info-row  { display:flex; justify-content:space-between; padding:3px 0; border-bottom:1px solid var(--border); }
.info-row:last-child { border-bottom:none; }
.info-row label { color:var(--text-dim); }
.info-row b     { color:var(--text); }

.layout-main { display:flex; flex:1; overflow:hidden; }
</style>
</head>
<body>

<div class="layout-header">
  <a href="<?= htmlspecialchars($cfg['back']) ?>" class="btn btn-secondary btn-sm">← 管理画面に戻る</a>
  <span class="sep">|</span>
  <h1>レイアウト編集 — <?= htmlspecialchars($cfg['title']) ?></h1>
  <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
    <button class="btn btn-secondary btn-sm" onclick="resetView()">🔍 全体表示</button>
    <button class="btn btn-accent2 btn-sm" onclick="openViewBoard()">🖥 ビュー確認</button>
    <button class="btn btn-success" onclick="saveLayout()">💾 保存</button>
  </div>
</div>

<!-- ページタブ -->
<div class="layout-page-tabs" id="layoutPageTabs">
  <span class="tab-label">ページ:</span>
</div>

<div class="layout-toolbar">
  <span>ドラッグで移動 / 角・辺のハンドルでリサイズ</span>
  <span id="zoomLabel">表示倍率: 50%</span>
  <button class="btn btn-secondary btn-sm" onclick="zoom(-0.05)">－</button>
  <button class="btn btn-secondary btn-sm" onclick="zoom(+0.05)">＋</button>
  <div class="coord-display" id="coordDisplay">パネルを選択してください</div>
</div>

<div class="layout-main">
  <div class="layout-canvas-wrap" id="canvasWrap">
    <div class="layout-board" id="layoutBoard">
      <span class="board-label"><?= $boardW ?> × <?= $boardH ?></span>
    </div>
  </div>

  <aside class="layout-side">
    <div>
      <h3>パネル一覧</h3>
      <div class="side-panel-list" id="sidePanelList"></div>
    </div>
    <div id="selectedInfo" style="display:none">
      <h3>選択中</h3>
      <div class="info-card" id="infoCard"></div>
    </div>
  </aside>
</div>

<div class="toast" id="toast"></div>

<?php if ($dbError): ?>
<div style="position:fixed;top:60px;left:50%;transform:translateX(-50%);
            background:#c62828;color:#fff;padding:10px 20px;border-radius:6px;z-index:9999;font-size:13px;">
  ⚠ DB接続エラー: <?= htmlspecialchars($dbError) ?>
</div>
<?php endif; ?>

<script src="../assets/js/api.js"></script>
<script>
const BOARD_KEY  = '<?= $boardKey ?>';
const BOARD_W    = <?= $boardW ?>;
const BOARD_H    = <?= $boardH ?>;
const ADMIN_BOARD_KEY = BOARD_KEY;
const MIN_SIZE   = 50;
const VIEW_URL   = '<?= $cfg['view'] ?>';

let panels      = <?= $panelsJson ?>;
let pages       = <?= $pagesJson ?>;
let activeId    = null;
let scale       = 0.5;
let currentPage = 1;

const TYPE_ICONS = {
  media:'🖼', text:'📝', accident:'🏆', notice:'📢',
  responsible:'🪧', disaster:'🚨'
};
const TYPE_LABELS = {
  media:'メディア', text:'テキスト', accident:'無災害記録', notice:'告知',
  responsible:'責任者掲示', disaster:'災害速報'
};

// ---- ページタブ ----
function renderPageTabs() {
  const bar = document.getElementById('layoutPageTabs');
  bar.innerHTML = `<span class="tab-label">ページ:</span>` +
    pages.map(pg =>
      `<button class="lp-tab ${pg.page_number === currentPage ? 'active' : ''}"
               onclick="switchPage(${pg.page_number})">${escHtml(pg.page_name)}</button>`
    ).join('');
}

function switchPage(n) {
  currentPage = n;
  activeId    = null;
  document.getElementById('selectedInfo').style.display = 'none';
  document.getElementById('coordDisplay').textContent = 'パネルを選択してください';
  renderPageTabs();
  renderBoard();
  renderSideList();
}

// ---- スケール ----
function applyScale() {
  document.getElementById('layoutBoard').style.transform = `scale(${scale})`;
  document.getElementById('zoomLabel').textContent = `表示倍率: ${Math.round(scale*100)}%`;
  const wrap = document.getElementById('canvasWrap');
  wrap.style.minWidth = Math.round(BOARD_W * scale + 40) + 'px';
}
function zoom(delta) {
  scale = Math.min(1, Math.max(0.2, scale + delta));
  applyScale();
}
function resetView() {
  const wrap = document.getElementById('canvasWrap');
  scale = Math.min(1, (wrap.clientWidth - 40) / BOARD_W);
  applyScale();
}

// ---- ボード描画（現在ページのみ） ----
function renderBoard() {
  const board = document.getElementById('layoutBoard');
  const label = board.querySelector('.board-label');
  board.innerHTML = '';
  board.appendChild(label);

  const pagePanels = panels.filter(p => (p.page || 1) === currentPage);
  pagePanels.forEach((p, idx) => board.appendChild(createPanelEl(p, idx)));
}

function createPanelEl(p, zIdx) {
  const el = document.createElement('div');
  el.className  = `le-panel type-${p.type} ${p.title ? '' : 'no-title'}`;
  el.dataset.id = p.id;
  el.style.cssText = `left:${p.x}px;top:${p.y}px;width:${p.width}px;height:${p.height}px;z-index:${zIdx+1}`;

  el.innerHTML = `
    <div class="le-panel-title">${TYPE_ICONS[p.type]||''} ${escHtml(p.title || TYPE_LABELS[p.type]||p.type)}</div>
    <div class="le-panel-body">${panelBodyHtml(p)}</div>
    <div class="le-resize nw" data-dir="nw"></div>
    <div class="le-resize n"  data-dir="n"></div>
    <div class="le-resize ne" data-dir="ne"></div>
    <div class="le-resize e"  data-dir="e"></div>
    <div class="le-resize se" data-dir="se"></div>
    <div class="le-resize s"  data-dir="s"></div>
    <div class="le-resize sw" data-dir="sw"></div>
    <div class="le-resize w"  data-dir="w"></div>`;

  setupDragResize(el, p);
  el.addEventListener('pointerdown', e => {
    if (!e.target.classList.contains('le-resize')) selectPanel(p.id);
  });
  return el;
}

function panelBodyHtml(p) {
  const c = p.content || {};
  switch (p.type) {
    case 'media':
      if (!c.filePath) return `<div class="placeholder">🖼 画像なし</div>`;
      if ((c.fileType||'').includes('pdf')) return `<div class="placeholder">📄 ${escHtml(c.fileName||'PDF')}</div>`;
      return `<img src="${c.filePath}" alt="">`;
    case 'text':
    case 'disaster':
      return `<div style="position:absolute;inset:0;padding:8px;color:rgba(255,255,255,.85);font-size:14px;white-space:pre-wrap;word-break:break-all;overflow:hidden;">
        ${escHtml(c.text||'') || `<span style="opacity:.4">${p.type==='disaster'?'速報':'テキスト'}なし</span>`}
      </div>`;
    case 'accident': {
      const elapsed = Math.max(0, Math.floor((Date.now() - new Date(c.startDate||new Date()).getTime()) / 86400000));
      return `<div class="accident-num">${elapsed.toLocaleString()}<br><span style="font-size:.5em;opacity:.7">日</span></div>`;
    }
    case 'notice': {
      const active = (c.notices||[]).filter(n => {
        const t = new Date(); t.setHours(0,0,0,0);
        if (n.startDate && new Date(n.startDate) > t) return false;
        if (n.endDate   && new Date(n.endDate)   < t) return false;
        return true;
      });
      if (!active.length) return `<div class="placeholder">📢 告知なし</div>`;
      return `<div style="padding:4px;width:100%;overflow:hidden;">${active.slice(0,3).map(n=>
        `<div style="font-size:9px;color:rgba(255,255,255,.8);border-left:2px solid #4fc3f7;padding:1px 4px;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
          ${escHtml(n.title||n.text||'')}
        </div>`).join('')}</div>`;
    }
    case 'responsible': {
      const role = escHtml(c.role || '化学物質管理者');
      const name = escHtml(c.name || '');
      const fs   = (c.fontSize || 40) + 'px';
      return `<div style="display:flex;width:100%;height:100%;background:#FFD700;">
        <div style="flex:1;background:#fff;margin:8%;display:flex;align-items:center;justify-content:center;writing-mode:vertical-rl;font-size:${fs};font-weight:bold;color:#111;overflow:hidden;border:2px solid #e0b800;">${name}</div>
        <div style="writing-mode:vertical-rl;font-size:${fs};font-weight:bold;color:#111;padding:6% 5% 6% 2%;white-space:nowrap;align-self:stretch;">${role}</div>
      </div>`;
    }
    default: return '';
  }
}

// ---- ドラッグ & リサイズ ----
function setupDragResize(el, panelData) {
  let mode = null, dir = null;
  let startX, startY, origL, origT, origW, origH;

  function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

  function commit(l, t, w, h) {
    l = clamp(l, 0, BOARD_W - MIN_SIZE);
    t = clamp(t, 0, BOARD_H - MIN_SIZE);
    w = clamp(w, MIN_SIZE, BOARD_W - l);
    h = clamp(h, MIN_SIZE, BOARD_H - t);
    el.style.left   = l + 'px';
    el.style.top    = t + 'px';
    el.style.width  = w + 'px';
    el.style.height = h + 'px';
    panelData.x = l; panelData.y = t;
    panelData.width = w; panelData.height = h;
    updateCoordDisplay(panelData);
    updateInfoCard(panelData);
  }

  el.addEventListener('pointerdown', e => {
    if (e.target.classList.contains('le-resize')) return;
    mode  = 'drag';
    origL = parseInt(el.style.left); origT = parseInt(el.style.top);
    startX = e.clientX / scale; startY = e.clientY / scale;
    el.setPointerCapture(e.pointerId);
    e.preventDefault();
  });

  el.querySelectorAll('.le-resize').forEach(h => {
    h.addEventListener('pointerdown', e => {
      mode  = 'resize'; dir = h.dataset.dir;
      origL = parseInt(el.style.left); origT = parseInt(el.style.top);
      origW = parseInt(el.style.width); origH = parseInt(el.style.height);
      startX = e.clientX / scale; startY = e.clientY / scale;
      h.setPointerCapture(e.pointerId);
      e.stopPropagation(); e.preventDefault();
    });
  });

  el.addEventListener('pointermove', e => {
    if (!mode) return;
    const dx = e.clientX / scale - startX;
    const dy = e.clientY / scale - startY;
    if (mode === 'drag') {
      commit(origL + dx, origT + dy, parseInt(el.style.width), parseInt(el.style.height));
    } else {
      let l=origL, t=origT, w=origW, h=origH;
      if (dir.includes('e')) w = origW + dx;
      if (dir.includes('s')) h = origH + dy;
      if (dir.includes('w')) { l = origL + dx; w = origW - dx; }
      if (dir.includes('n')) { t = origT + dy; h = origH - dy; }
      commit(l, t, w, h);
    }
  });

  el.addEventListener('pointerup',    () => { mode = null; dir = null; });
  el.addEventListener('pointercancel',() => { mode = null; dir = null; });
}

// ---- 選択 ----
function selectPanel(id) {
  activeId = id;
  document.querySelectorAll('.le-panel').forEach(el =>
    el.classList.toggle('active', el.dataset.id === id));
  document.querySelectorAll('.side-panel-item').forEach(el =>
    el.classList.toggle('active', el.dataset.id === id));
  const p = panels.find(p => p.id === id);
  if (p) {
    updateCoordDisplay(p);
    updateInfoCard(p);
    document.getElementById('selectedInfo').style.display = '';
  }
}

function updateCoordDisplay(p) {
  const pg = pages.find(pg => pg.page_number === (p.page || 1));
  document.getElementById('coordDisplay').innerHTML =
    `<b>${escHtml(p.title || TYPE_LABELS[p.type]||'')}</b> &nbsp;|&nbsp;
     X:<b>${p.x}</b> Y:<b>${p.y}</b> 幅:<b>${p.width}</b> 高:<b>${p.height}</b>
     &nbsp;| ページ:<b>${escHtml(pg?.page_name || String(p.page||1))}</b>`;
}

function updateInfoCard(p) {
  const pg = pages.find(pg => pg.page_number === (p.page || 1));
  document.getElementById('infoCard').innerHTML = `
    <div class="info-row"><label>種別</label><b>${TYPE_LABELS[p.type]||p.type}</b></div>
    <div class="info-row"><label>ページ</label><b>${escHtml(pg?.page_name || String(p.page||1))}</b></div>
    <div class="info-row"><label>X</label><b>${p.x} px</b></div>
    <div class="info-row"><label>Y</label><b>${p.y} px</b></div>
    <div class="info-row"><label>幅</label><b>${p.width} px</b></div>
    <div class="info-row"><label>高さ</label><b>${p.height} px</b></div>`;
}

// ---- サイドリスト（現在ページのみ） ----
function renderSideList() {
  const pagePanels = panels.filter(p => (p.page || 1) === currentPage);
  document.getElementById('sidePanelList').innerHTML = pagePanels.length
    ? pagePanels.map(p => `
        <div class="side-panel-item" data-id="${p.id}" onclick="selectPanel('${p.id}')">
          <span>${TYPE_ICONS[p.type]||'□'}</span>
          <span class="name">${escHtml(p.title || TYPE_LABELS[p.type]||p.type)}</span>
        </div>`).join('')
    : `<div style="font-size:12px;color:var(--text-dim);padding:8px;">このページにパネルがありません</div>`;
}

// ---- 保存（全ページのパネルを一括保存） ----
async function saveLayout() {
  try {
    const json = await API.savePanels(panels, BOARD_KEY);
    if (json.ok) showToast('レイアウトを保存しました');
    else         showToast('保存失敗: ' + (json.error||'不明'), true);
  } catch(e) {
    showToast('通信エラー: ' + e.message, true);
  }
}

function openViewBoard() {
  window.open(VIEW_URL, '_blank');
}

// ---- ユーティリティ ----
function escHtml(s) {
  return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function showToast(msg, isErr=false) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.toggle('toast-err', isErr);
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

// ---- キーボード移動 ----
document.addEventListener('keydown', e => {
  if (!activeId) return;
  const p = panels.find(p => p.id === activeId);
  if (!p) return;
  const step = e.shiftKey ? 10 : 1;
  const el = document.querySelector(`.le-panel[data-id="${activeId}"]`);
  if (!el) return;
  const moves = { ArrowLeft:[-step,0], ArrowRight:[step,0], ArrowUp:[0,-step], ArrowDown:[0,step] };
  if (moves[e.key]) {
    const [dx, dy] = moves[e.key];
    p.x = Math.max(0, Math.min(BOARD_W - p.width,  p.x + dx));
    p.y = Math.max(0, Math.min(BOARD_H - p.height, p.y + dy));
    el.style.left = p.x + 'px';
    el.style.top  = p.y + 'px';
    updateCoordDisplay(p);
    updateInfoCard(p);
    e.preventDefault();
  }
});

document.addEventListener('DOMContentLoaded', () => {
  renderPageTabs();
  resetView();
  renderBoard();
  renderSideList();
});
</script>
</body>
</html>
