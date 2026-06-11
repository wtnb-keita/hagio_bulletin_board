<?php
require_once __DIR__ . '/../../api/db.php';

const BOARD_KEY = 'staff_board';

$boardConfig = ['name' => '安全資格者掲示板', 'width' => 1800, 'height' => 900];
try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM boards WHERE board_key = ?');
    $stmt->execute([BOARD_KEY]);
    $row  = $stmt->fetch();
    if ($row) $boardConfig = ['name' => $row['name'], 'width' => (int)$row['width'], 'height' => (int)$row['height']];
} catch (Throwable $e) {}

$staffList = [];
$dbError   = '';
try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE board_key = ? ORDER BY sort_order, id');
    $stmt->execute([BOARD_KEY]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $qs = $pdo->prepare('SELECT name FROM staff_qualifications WHERE staff_id = ? ORDER BY sort_order, id');
        $qs->execute([$row['id']]);
        $quals = array_column($qs->fetchAll(), 'name');
        $photoPath = $row['photo_path'] ? uploadUrlBase() . basename($row['photo_path']) : '';
        $staffList[] = [
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'department'     => $row['department'],
            'photoPath'      => $photoPath,
            'qualifications' => $quals,
            'sortOrder'      => (int)$row['sort_order'],
        ];
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$baseUrl   = rtrim(str_replace('\\', '/', str_replace(rtrim($_SERVER['DOCUMENT_ROOT'],'/\\'), '', dirname(dirname(dirname(__FILE__))))), '/');
$staffJson = json_encode($staffList, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>管理画面 - <?= htmlspecialchars($boardConfig['name']) ?></title>
<link rel="stylesheet" href="../../assets/css/common.css">
<link rel="stylesheet" href="../../assets/css/admin.css">
<style>
/* ===== レイアウト ===== */
.admin-body {
  display: flex;
  height: calc(100vh - 110px);
  overflow: hidden;
}
.panel-left {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  border-right: 1px solid var(--border);
}
.panel-right {
  width: 360px;
  flex-shrink: 0;
  overflow-y: auto;
  background: var(--surface);
  padding: 20px;
}

/* ===== スタッフカード一覧 ===== */
.staff-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
}
.staff-card {
  background: var(--surface2);
  border: 2px solid var(--border);
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  position: relative;
  transition: border-color .15s;
}
.staff-card:hover         { border-color: var(--accent); }
.staff-card.is-selected   { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(233,69,96,.25); }
.staff-card-thumb {
  height: 120px;
  background: var(--surface);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.staff-card-thumb img    { width: 100%; height: 100%; object-fit: cover; }
.staff-card-thumb .icon  { font-size: 40px; color: var(--text-dim); }
.staff-card-body         { padding: 8px 10px; }
.staff-card-num {
  position: absolute;
  top: 5px; left: 5px;
  background: rgba(0,0,0,.55);
  color: #fff;
  font-size: 11px;
  font-weight: bold;
  padding: 1px 6px;
  border-radius: 3px;
}
.staff-card-del {
  position: absolute;
  top: 5px; right: 5px;
  width: 22px; height: 22px;
  background: rgba(180,30,30,.85);
  color: #fff;
  border: none;
  border-radius: 50%;
  font-size: 12px;
  cursor: pointer;
  display: none;
  align-items: center;
  justify-content: center;
}
.staff-card:hover .staff-card-del { display: flex; }
.staff-card-name { font-size: 14px; font-weight: bold; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.staff-card-dept { font-size: 11px; color: var(--text-dim); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.staff-card-add {
  border: 2px dashed var(--border);
  border-radius: 8px;
  height: 170px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  color: var(--text-dim);
  font-size: 13px;
  transition: border-color .15s, color .15s;
}
.staff-card-add:hover { border-color: var(--accent); color: var(--accent); }

/* ===== 右パネル：エディタ ===== */
.editor-placeholder {
  color: var(--text-dim);
  font-size: 13px;
  text-align: center;
  padding: 60px 0;
}
.editor-title {
  font-size: 16px;
  font-weight: bold;
  color: var(--text);
  margin-bottom: 16px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border);
}

/* 写真プレビュー */
.photo-box {
  width: 100%;
  height: 140px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 6px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 8px;
}
.photo-box img        { width: 100%; height: 100%; object-fit: cover; display: block; }
.photo-box .ph-icon   { font-size: 40px; color: var(--text-dim); }
.photo-btns           { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 16px; }

/* 資格リスト */
.qual-list            { display: flex; flex-direction: column; gap: 6px; margin-bottom: 8px; }
.qual-row             { display: flex; gap: 6px; align-items: center; }
.qual-row input       { flex: 1; }
.qual-row .del-btn {
  background: none;
  border: 1px solid var(--border);
  color: var(--text-dim);
  border-radius: 4px;
  padding: 4px 8px;
  cursor: pointer;
  font-size: 12px;
  flex-shrink: 0;
}
.qual-row .del-btn:hover { border-color: var(--accent); color: var(--accent); }

/* ===== 写真ライブラリモーダル ===== */
#photoLibModal .modal  { width: 720px; max-width: 95vw; }
.lib-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 8px;
  max-height: 360px;
  overflow-y: auto;
  padding: 4px;
  margin-top: 4px;
}
.lib-item {
  aspect-ratio: 1;
  border: 2px solid var(--border);
  border-radius: 6px;
  overflow: hidden;
  cursor: pointer;
  transition: border-color .15s;
}
.lib-item:hover       { border-color: var(--accent); }
.lib-item img         { width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none; }
</style>
</head>
<body>

<header class="admin-header">
  <h1><?= htmlspecialchars($boardConfig['name']) ?> 管理画面</h1>
  <div class="header-actions">
    <button class="btn btn-accent2" onclick="openView()">🖥 ビュー画面を開く</button>
    <button class="btn btn-success"  onclick="saveAll()">💾 保存</button>
  </div>
</header>

<nav class="admin-nav">
  <a href="<?= rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') ?>/safetynotice_board_no1/index.php">安全掲示板 No.1</a>
  <a href="<?= rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') ?>/safetynotice_board_no2/index.php">安全掲示板 No.2</a>
  <a href="<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/index.php" class="active">安全資格者掲示板</a>
</nav>

<div class="admin-body">

  <!-- 左：スタッフ一覧 -->
  <div class="panel-left">
    <div class="staff-grid" id="staffGrid"></div>
  </div>

  <!-- 右：編集エリア -->
  <div class="panel-right" id="editorPanel">
    <div class="editor-placeholder">← スタッフを選択して編集</div>
  </div>

</div>

<!-- トースト -->
<div class="toast" id="toast"></div>

<!-- DB エラー -->
<?php if ($dbError): ?>
<div style="position:fixed;top:60px;left:50%;transform:translateX(-50%);background:#c62828;color:#fff;padding:10px 20px;border-radius:6px;z-index:9999;font-size:13px;">
  ⚠ DB接続エラー: <?= htmlspecialchars($dbError) ?>
</div>
<?php endif; ?>

<!-- ===== 写真ライブラリモーダル ===== -->
<div class="modal-overlay" id="photoLibModal">
  <div class="modal">
    <div class="modal-header">
      <h2>📁 写真を選択</h2>
      <div style="margin-left:auto;display:flex;gap:8px;align-items:center;">
        <label class="btn btn-primary btn-sm" style="cursor:pointer;">
          ＋ 新規アップロード
          <input type="file" id="libFileInput" accept="image/*" style="display:none;" onchange="libUpload(event)">
        </label>
        <button class="btn btn-secondary btn-sm" onclick="closeLib()">✕ 閉じる</button>
      </div>
    </div>
    <div class="lib-grid" id="libGrid">
      <div style="color:var(--text-dim);font-size:13px;padding:12px;">読み込み中...</div>
    </div>
    <p style="font-size:11px;color:var(--text-dim);margin-top:10px;">クリックして写真に設定します</p>
  </div>
</div>

<script src="../../assets/js/api.js"></script>
<script>
const BASE_URL  = '<?= $baseUrl ?>';
const BOARD_KEY = 'staff_board';
const VIEW_URL  = '<?= $baseUrl ?>/view_board/staff_board/index.php';

let staffList   = <?= $staffJson ?>;
let selectedIdx = null;

/* ===== トースト ===== */
function toast(msg, err = false) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast toast-show' + (err ? ' toast-error' : '');
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('toast-show'), 3000);
}

/* ===== ビュー画面 ===== */
function openView() { window.open(VIEW_URL, '_blank'); }

/* ===== 共通エスケープ ===== */
function eh(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ===== グリッド描画 ===== */
function renderGrid() {
  const grid = document.getElementById('staffGrid');
  grid.innerHTML = '';

  staffList.forEach((s, i) => {
    const card = document.createElement('div');
    card.className = 'staff-card' + (i === selectedIdx ? ' is-selected' : '');
    card.addEventListener('click', () => selectStaff(i));

    const thumb = s.photoPath
      ? `<img src="${eh(s.photoPath)}" alt="">`
      : `<div class="icon">👤</div>`;

    card.innerHTML = `
      <span class="staff-card-num">${i + 1}</span>
      <button class="staff-card-del" title="削除" onclick="event.stopPropagation(); doDelete(${i})">✕</button>
      <div class="staff-card-thumb">${thumb}</div>
      <div class="staff-card-body">
        <div class="staff-card-name">${eh(s.name || '（名前未設定）')}</div>
        <div class="staff-card-dept">${eh(s.department || '')}</div>
      </div>`;
    grid.appendChild(card);
  });

  const add = document.createElement('div');
  add.className = 'staff-card-add';
  add.innerHTML = '<span style="font-size:28px;">＋</span><span>スタッフを追加</span>';
  add.addEventListener('click', addStaff);
  grid.appendChild(add);
}

/* ===== スタッフ選択 ===== */
function selectStaff(idx) {
  flushEditor();
  selectedIdx = idx;
  renderGrid();
  renderEditor();
}

/* ===== エディタ描画 ===== */
function renderEditor() {
  const panel = document.getElementById('editorPanel');

  if (selectedIdx === null || !staffList[selectedIdx]) {
    panel.innerHTML = '<div class="editor-placeholder">← スタッフを選択して編集</div>';
    return;
  }

  const s = staffList[selectedIdx];

  const photoHtml = s.photoPath
    ? `<img src="${eh(s.photoPath)}" alt="写真">`
    : `<div class="ph-icon">📷</div>`;

  const qualsHtml = (s.qualifications || []).map((q, qi) => `
    <div class="qual-row">
      <input type="text" value="${eh(q)}" placeholder="資格名"
        oninput="staffList[${selectedIdx}].qualifications[${qi}] = this.value">
      <button class="del-btn" onclick="removeQual(${qi})">✕</button>
    </div>`).join('');

  panel.innerHTML = `
    <div class="editor-title">スタッフ編集 <small style="font-weight:normal;color:var(--text-dim)">No.${selectedIdx + 1}</small></div>

    <div class="form-group">
      <label>写真</label>
      <div class="photo-box" id="photoBox">${photoHtml}</div>
      <div class="photo-btns">
        <label class="btn btn-primary btn-sm" style="cursor:pointer;color:#fff;">
          ＋ アップロード
          <input type="file" accept="image/*" style="display:none;" onchange="uploadPhoto(event)">
        </label>
        <button class="btn btn-secondary btn-sm" onclick="openLib()">📁 ライブラリから選択</button>
        ${s.photoPath ? `<button class="btn btn-secondary btn-sm" onclick="clearPhoto()">✕ 写真を削除</button>` : ''}
      </div>
    </div>

    <div class="form-group">
      <label>名前 <span style="color:var(--accent);">*</span></label>
      <input type="text" id="f_name" value="${eh(s.name)}" placeholder="氏名を入力">
    </div>

    <div class="form-group">
      <label>血液型</label>
      <input type="text" id="f_dept" value="${eh(s.department || '')}" placeholder="例：A型">
    </div>

    <div class="form-group">
      <label>資格</label>
      <div class="qual-list" id="qualList">${qualsHtml}</div>
      <button class="btn btn-secondary btn-sm" onclick="addQual()">＋ 資格を追加</button>
    </div>

    <div style="margin-top:16px;">
      <button class="btn btn-success" style="width:100%;" onclick="saveAll()">💾 保存</button>
    </div>`;
}

/* ===== エディタ → staffList に反映 ===== */
function flushEditor() {
  if (selectedIdx === null) return;
  const n = document.getElementById('f_name');
  const d = document.getElementById('f_dept');
  if (!n) return;
  staffList[selectedIdx].name       = n.value.trim();
  staffList[selectedIdx].department = d ? d.value.trim() : '';
}

/* ===== 写真アップロード ===== */
async function uploadPhoto(event) {
  const file = event.target.files[0];
  if (!file || selectedIdx === null) return;
  toast('アップロード中...');
  try {
    const res = await API.uploadFile(file);
    if (res.filePath) {
      staffList[selectedIdx].photoPath = res.filePath;
      renderEditor();
      toast('写真をアップロードしました');
    }
  } catch(e) { toast('アップロード失敗: ' + e.message, true); }
}

function clearPhoto() {
  if (selectedIdx === null) return;
  staffList[selectedIdx].photoPath = '';
  renderEditor();
}

/* ===== 写真ライブラリ ===== */
function openLib() {
  document.getElementById('photoLibModal').classList.add('open');
  loadLib();
}
function closeLib() {
  document.getElementById('photoLibModal').classList.remove('open');
}

async function loadLib() {
  const grid = document.getElementById('libGrid');
  grid.innerHTML = '<div style="color:var(--text-dim);font-size:13px;padding:12px;">読み込み中...</div>';
  try {
    const data  = await API.getUploads();
    const files = (data.files || []).filter(f => f.fileType && f.fileType.startsWith('image/'));
    if (!files.length) {
      grid.innerHTML = '<div style="color:var(--text-dim);font-size:13px;padding:12px;">画像がありません</div>';
      return;
    }
    grid.innerHTML = '';
    files.forEach(f => {
      const div = document.createElement('div');
      div.className = 'lib-item';
      div.title     = f.fileName;
      div.innerHTML = `<img src="${eh(f.filePath)}" alt="${eh(f.fileName)}" loading="lazy">`;
      div.addEventListener('click', () => {
        if (selectedIdx !== null) {
          staffList[selectedIdx].photoPath = f.filePath;
          renderEditor();
        }
        closeLib();
      });
      grid.appendChild(div);
    });
  } catch(e) {
    grid.innerHTML = `<div style="color:var(--accent);font-size:13px;padding:12px;">読み込み失敗: ${eh(e.message)}</div>`;
  }
}

async function libUpload(event) {
  const file = event.target.files[0];
  if (!file) return;
  toast('アップロード中...');
  try {
    const res = await API.uploadFile(file);
    if (res.filePath) { toast('アップロードしました'); await loadLib(); }
  } catch(e) { toast('アップロード失敗: ' + e.message, true); }
  event.target.value = '';
}

/* ===== 資格 ===== */
function addQual() {
  if (selectedIdx === null) return;
  staffList[selectedIdx].qualifications = staffList[selectedIdx].qualifications || [];
  staffList[selectedIdx].qualifications.push('');
  const list = document.getElementById('qualList');
  const qi   = staffList[selectedIdx].qualifications.length - 1;
  const row  = document.createElement('div');
  row.className = 'qual-row';
  row.innerHTML = `
    <input type="text" placeholder="資格名"
      oninput="staffList[${selectedIdx}].qualifications[${qi}] = this.value">
    <button class="del-btn" onclick="removeQual(${qi})">✕</button>`;
  list.appendChild(row);
  row.querySelector('input').focus();
}

function removeQual(qi) {
  if (selectedIdx === null) return;
  staffList[selectedIdx].qualifications.splice(qi, 1);
  renderEditor();
}

/* ===== スタッフ追加 ===== */
function addStaff() {
  flushEditor();
  staffList.push({ name: '', department: '', photoPath: '', qualifications: [] });
  selectedIdx = staffList.length - 1;
  renderGrid();
  renderEditor();
  document.getElementById('f_name')?.focus();
}

/* ===== スタッフ削除 ===== */
async function doDelete(idx) {
  if (!confirm(`「${staffList[idx].name || '（名前未設定）'}」を削除しますか？`)) return;
  staffList.splice(idx, 1);
  if (selectedIdx === idx)       selectedIdx = null;
  else if (selectedIdx > idx)    selectedIdx--;
  renderGrid();
  renderEditor();
  await saveAll(true);
}

/* ===== 保存 ===== */
async function saveAll(silent = false) {
  if (!silent) flushEditor();
  const valid = staffList.filter(s => s.name.trim() !== '');
  try {
    const res  = await fetch(`${BASE_URL}/api/staff.php?board=${BOARD_KEY}`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ staff: valid }),
    });
    const json = await res.json();
    if (json.ok) {
      staffList = valid;
      if (selectedIdx !== null && selectedIdx >= staffList.length) selectedIdx = null;
      renderGrid();
      toast(silent ? '削除しました' : '保存しました');
    } else {
      toast('保存失敗: ' + (json.error || '不明'), true);
    }
  } catch(e) {
    toast('通信エラー: ' + e.message, true);
  }
}

/* ===== 初期描画 ===== */
renderGrid();
</script>
</body>
</html>
