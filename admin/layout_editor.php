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
<link rel="stylesheet" href="../assets/css/view-board.css">
<style>
/* view-board.css のbodyスタイルを上書き */
body { width: auto !important; height: 100vh !important; background: var(--bg) !important;
       display: flex; flex-direction: column; overflow: hidden; }

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

/* ---- パネルラッパー（ドラッグ・選択用） ---- */
.le-panel {
  position: absolute;
  outline: 2px solid rgba(255,255,255,0.25);
  outline-offset: -2px;
  border-radius: 6px;
  cursor: move;
  user-select: none;
  overflow: visible;
  transition: outline-color .1s, box-shadow .1s;
}
.le-panel:hover  { outline-color: rgba(255,255,255,0.7); }
.le-panel.active {
  outline: 3px solid #fff;
  outline-offset: 2px;
  box-shadow: 0 0 0 4px var(--accent), 0 0 24px rgba(46,125,50,0.6);
  /* z-index はレイヤー順を維持するため上書きしない */
}

/* viewのCSSをle-panel内で上書き（inline styleも!importantで無効化） */
.le-panel .panel {
  position: absolute !important;
  left:   0 !important;
  top:    0 !important;
  right:  0 !important;
  bottom: 0 !important;
  width:  auto !important;
  height: auto !important;
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
.layer-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 4px;
  border-radius: 4px;
  background: var(--surface2);
  border: 1px solid var(--border);
  font-size: 10px;
  font-weight: bold;
  color: var(--text-dim);
  flex-shrink: 0;
}

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
<script src="../assets/js/panel-render.js"></script>
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
  responsible:'🪧', disaster:'🚨', hazard:'⚠️', label:'🏷️'
};
const TYPE_LABELS = {
  media:'メディア', text:'テキスト', accident:'無災害記録', notice:'告知',
  responsible:'責任者掲示', disaster:'災害速報', hazard:'警戒枠', label:'カラーラベル'
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

// ---- ボード描画（現在ページのみ・sort_order順） ----
function renderBoard() {
  const board = document.getElementById('layoutBoard');
  const label = board.querySelector('.board-label');
  board.innerHTML = '';
  board.appendChild(label);

  const pagePanels = panels
    .filter(p => (p.page || 1) === currentPage)
    .slice()
    .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
  pagePanels.forEach(p => board.appendChild(createPanelEl(p)));
}

function createPanelEl(p) {
  const el = document.createElement('div');
  el.className  = 'le-panel';
  el.dataset.id = p.id;
  const layer = p.sort_order ?? 1;
  el.style.cssText = `left:${p.x}px;top:${p.y}px;width:${p.width}px;height:${p.height}px;z-index:${layer}`;

  // viewと同じ描画を使用
  const inner = PanelRender.createPanelElement(p);
  el.appendChild(inner);

  // リサイズハンドル
  ['nw','n','ne','e','se','s','sw','w'].forEach(d => {
    const h = document.createElement('div');
    h.className = `le-resize ${d}`;
    h.dataset.dir = d;
    el.appendChild(h);
  });

  setupDragResize(el, p);
  el.addEventListener('pointerdown', e => {
    if (!e.target.classList.contains('le-resize')) selectPanel(p.id);
  });
  return el;
}

// panelBodyHtml は PanelRender に移行したため削除

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
  const layer = p.sort_order ?? 0;
  document.getElementById('infoCard').innerHTML = `
    <div class="info-row"><label>種別</label><b>${TYPE_LABELS[p.type]||p.type}</b></div>
    <div class="info-row"><label>ページ</label><b>${escHtml(pg?.page_name || String(p.page||1))}</b></div>
    <div class="info-row"><label>X</label><b>${p.x} px</b></div>
    <div class="info-row"><label>Y</label><b>${p.y} px</b></div>
    <div class="info-row"><label>幅</label><b>${p.width} px</b></div>
    <div class="info-row"><label>高さ</label><b>${p.height} px</b></div>
    <div class="info-row"><label>レイヤー</label><b id="layerVal">${layer}</b></div>
    <div style="display:flex;gap:6px;margin-top:8px;">
      <button class="btn btn-secondary btn-sm" style="flex:1" onclick="moveLayer('${p.id}', 1)">▲ 前面へ</button>
      <button class="btn btn-secondary btn-sm" style="flex:1" onclick="moveLayer('${p.id}', -1)">▼ 背面へ</button>
    </div>`;
}

function moveLayer(id, dir) {
  const pagePanels = panels
    .filter(p => (p.page||1) === currentPage)
    .sort((a, b) => (a.sort_order??0) - (b.sort_order??0));
  const idx = pagePanels.findIndex(p => p.id === id);
  if (idx < 0) return;
  const swapIdx = idx + dir;
  if (swapIdx < 0 || swapIdx >= pagePanels.length) return;
  const a = pagePanels[idx], b = pagePanels[swapIdx];
  const tmp = a.sort_order ?? idx;
  a.sort_order = b.sort_order ?? swapIdx;
  b.sort_order = tmp;
  renderBoard();
  renderSideList();
  selectPanel(id);
}

// ---- サイドリスト（レイヤー順・番号付き） ----
function renderSideList() {
  const pagePanels = panels
    .filter(p => (p.page || 1) === currentPage)
    .slice()
    .sort((a, b) => (b.sort_order ?? 0) - (a.sort_order ?? 0)); // 上が前面
  document.getElementById('sidePanelList').innerHTML = pagePanels.length
    ? pagePanels.map((p, i) => {
        const layerNum = pagePanels.length - i; // 前面=大きい番号
        return `
        <div class="side-panel-item ${p.id === activeId ? 'active' : ''}" data-id="${p.id}" onclick="selectPanel('${p.id}')">
          <span class="layer-badge" title="レイヤー ${layerNum}">${layerNum}</span>
          <span>${TYPE_ICONS[p.type]||'□'}</span>
          <span class="name">${escHtml(p.title || TYPE_LABELS[p.type]||p.type)}</span>
        </div>`;
      }).join('')
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
