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
            'jobType'        => $row['job_type'] ?? '',
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

/* ===== マスター設定モーダル ===== */
#masterModal .modal      { width: 640px; max-width: 95vw; }
.master-tabs             { display: flex; gap: 4px; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 0; }
.master-tab              { padding: 10px 24px; border: none; background: none; cursor: pointer; font-size: 14px; color: var(--text-dim); border-bottom: 2px solid transparent; margin-bottom: -1px; }
.master-tab.active       { color: var(--accent); border-bottom-color: var(--accent); font-weight: bold; }
.master-tab:hover        { color: var(--text); }
.master-panel            { display: none; }
.master-panel.active     { display: block; min-height: 200px; max-height: 400px; overflow-y: auto; }
.master-item-row         { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
.master-item-row input   { flex: 1; font-size: 15px; padding: 8px 10px; height: 40px; }
.master-item-row .del-btn { background: none; border: 1px solid var(--border); color: var(--text-dim); border-radius: 4px; padding: 0 12px; cursor: pointer; font-size: 14px; flex-shrink: 0; height: 40px; }
.master-item-row .del-btn:hover { border-color: var(--accent); color: var(--accent); }

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
  <button class="subtitle board-size-btn" onclick="openSlideSettings()"
          title="クリックして掲示板設定を変更"
          style="background:none;border:none;color:rgba(255,255,255,.7);cursor:pointer;font-size:13px;
                 padding:2px 6px;border-radius:3px;transition:background .15s;"
          onmouseover="this.style.background='rgba(255,255,255,.15)'"
          onmouseout="this.style.background='none'">
    <?= $boardConfig['width'] ?> × <?= $boardConfig['height'] ?>px ビュー ✎
  </button>
  <div class="header-actions">
    <button class="btn btn-secondary" onclick="openMasterSettings()">📋 マスター設定</button>
    <button class="btn btn-secondary" onclick="openSlideSettings()">🖼 スライドショー設定</button>
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

<!-- ===== 掲示板設定モーダル ===== -->
<div class="modal-overlay" id="slideModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;align-items:center;justify-content:center;">
  <div class="modal" style="width:380px;background:var(--surface);border-radius:8px;padding:20px;">
    <div class="modal-header" style="margin-bottom:16px;">
      <h2>⚙ ボード設定</h2>
      <button class="btn btn-secondary btn-sm" onclick="closeSlideSettings()" style="margin-left:auto">✕</button>
    </div>
    <div class="form-group" style="margin-bottom:12px">
      <label>掲示板名</label>
      <input type="text" id="ss_name" value="<?= htmlspecialchars($boardConfig['name']) ?>">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>幅 (px)</label>
        <input type="number" id="ss_width" value="1800" min="400" max="7680" step="1">
      </div>
      <div class="form-group">
        <label>高さ (px)</label>
        <input type="number" id="ss_height" value="900" min="200" max="4320" step="1">
      </div>
    </div>
    <div style="margin-top:6px;margin-bottom:14px">
      <p style="font-size:11px;color:var(--text-dim);margin-bottom:6px">よく使うサイズ:</p>
      <div style="display:flex;flex-wrap:wrap;gap:6px">
        <button class="btn btn-secondary btn-sm" onclick="document.getElementById('ss_width').value=1920;document.getElementById('ss_height').value=1080">1920×1080 (FHD)</button>
        <button class="btn btn-secondary btn-sm" onclick="document.getElementById('ss_width').value=1800;document.getElementById('ss_height').value=900">1800×900</button>
        <button class="btn btn-secondary btn-sm" onclick="document.getElementById('ss_width').value=3840;document.getElementById('ss_height').value=2160">3840×2160 (4K)</button>
        <button class="btn btn-secondary btn-sm" onclick="document.getElementById('ss_width').value=1280;document.getElementById('ss_height').value=720">1280×720 (HD)</button>
      </div>
    </div>
    <div style="border-top:1px solid var(--border);margin-top:12px;padding-top:12px">
    <p style="font-size:12px;font-weight:bold;margin-bottom:10px;color:var(--text);">🖼 スライドショー設定</p>
    <p style="font-size:12px;color:var(--text-dim);margin-bottom:12px">
      スタッフ数が12人を超えると自動的に複数ページ（12人/ページ）に分かれます。
    </p>
    <div style="margin-bottom:12px">
      <button id="ss_enabled"
              data-enabled="0"
              onclick="toggleSsBtn()"
              style="display:inline-flex;align-items:center;gap:8px;
                     padding:8px 20px;border-radius:20px;
                     border:2px solid var(--border);background:var(--surface2);
                     color:var(--text-dim);font-size:13px;font-family:inherit;
                     cursor:pointer;transition:all .2s;font-weight:bold;">
        <span class="ss-dot" style="width:10px;height:10px;border-radius:50%;background:#aaa;display:inline-block;transition:background .2s;"></span>
        <span>ページ自動切り替え:</span>
        <span class="ss-label">無効</span>
      </button>
    </div>
    <div class="form-group" style="margin-bottom:0">
      <label>切り替え間隔（秒）</label>
      <input type="number" id="ss_interval" value="10" min="3" max="300" step="1">
    </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;border-top:1px solid var(--border);padding-top:12px;margin-top:12px">
      <button class="btn btn-secondary" onclick="closeSlideSettings()">キャンセル</button>
      <button class="btn btn-success" onclick="saveSlideSettings()">💾 保存</button>
    </div>
  </div>
</div>

<!-- ===== マスター設定モーダル ===== -->
<div class="modal-overlay" id="masterModal">
  <div class="modal">
    <div class="modal-header">
      <h2>📋 マスター設定</h2>
      <button class="btn btn-secondary btn-sm" onclick="closeMasterSettings()" style="margin-left:auto">✕ 閉じる</button>
    </div>
    <div class="master-tabs">
      <button class="master-tab active" onclick="switchMasterTab('job')">🏷 職種</button>
      <button class="master-tab"        onclick="switchMasterTab('qual')">📜 資格</button>
    </div>

    <!-- 職種タブ -->
    <div class="master-panel active" id="masterPanelJob">
      <div class="master-item-list" id="masterJobList"></div>
      <button class="btn btn-secondary btn-sm" style="margin-top:4px;" onclick="addMasterItem('job')">＋ 職種を追加</button>
    </div>

    <!-- 資格タブ -->
    <div class="master-panel" id="masterPanelQual">
      <div class="master-item-list" id="masterQualList"></div>
      <button class="btn btn-secondary btn-sm" style="margin-top:4px;" onclick="addMasterItem('qual')">＋ 資格を追加</button>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px;border-top:1px solid var(--border);padding-top:12px;margin-top:16px;">
      <button class="btn btn-secondary" onclick="closeMasterSettings()">キャンセル</button>
      <button class="btn btn-success"   onclick="saveMasterSettings()">💾 保存</button>
    </div>
  </div>
</div>

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
  el.className = 'toast' + (err ? ' toast-err' : '');
  requestAnimationFrame(() => el.classList.add('show'));
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 3000);
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
      <select id="f_dept">
        <option value="">（未設定）</option>
        ${['A型','B型','O型','AB型'].map(t => `<option value="${t}"${s.department===t?' selected':''}>${t}</option>`).join('')}
      </select>
    </div>

    <div class="form-group">
      <label>職種</label>
      ${masterJobTypes.length > 0
        ? `<select id="f_job_type">
            <option value="">（未設定）</option>
            ${masterJobTypes.map(t => `<option value="${eh(t)}"${s.jobType===t?' selected':''}>${eh(t)}</option>`).join('')}
            ${s.jobType && !masterJobTypes.includes(s.jobType) ? `<option value="${eh(s.jobType)}" selected>${eh(s.jobType)}</option>` : ''}
           </select>`
        : `<input type="text" id="f_job_type" value="${eh(s.jobType || '')}" placeholder="職種を入力（マスター設定で選択肢を追加できます）">`
      }
    </div>

    <div class="form-group">
      <label>資格</label>
      <div class="qual-list" id="qualList">${qualsHtml}</div>
      ${masterQualItems.length > 0 ? `
      <div style="display:flex;gap:6px;margin-top:4px;align-items:center;">
        <select id="qualMasterSelect" style="flex:1;">
          <option value="">マスターから追加▼</option>
          ${masterQualItems.map(q => `<option value="${eh(q)}">${eh(q)}</option>`).join('')}
        </select>
        <button class="btn btn-secondary btn-sm" onclick="addQualFromMaster()">追加</button>
      </div>` : ''}
      <button class="btn btn-secondary btn-sm" style="margin-top:6px;" onclick="addQual()">＋ 手入力で追加</button>
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
  const j = document.getElementById('f_job_type');
  staffList[selectedIdx].name       = n.value.trim();
  staffList[selectedIdx].department = d ? (d.value ?? '').trim() : '';
  staffList[selectedIdx].jobType    = j ? (j.value ?? '').trim() : '';
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

function addQualFromMaster() {
  const sel = document.getElementById('qualMasterSelect');
  if (!sel || !sel.value) return;
  const name = sel.value;
  sel.value = '';
  if (selectedIdx === null) return;
  staffList[selectedIdx].qualifications = staffList[selectedIdx].qualifications || [];
  staffList[selectedIdx].qualifications.push(name);
  renderEditor();
}

function removeQual(qi) {
  if (selectedIdx === null) return;
  staffList[selectedIdx].qualifications.splice(qi, 1);
  renderEditor();
}

/* ===== スタッフ追加 ===== */
function addStaff() {
  flushEditor();
  staffList.push({ name: '', department: '', jobType: '', photoPath: '', qualifications: [] });
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
      if (silent) {
        toast('削除しました');
      } else {
        toast('保存しました ✔');
        const btn = document.querySelector('.admin-header .btn-success');
        if (btn) {
          const orig = btn.textContent;
          btn.textContent = '✔ 保存済み';
          btn.disabled = true;
          setTimeout(() => { btn.textContent = orig; btn.disabled = false; }, 2000);
        }
      }
    } else {
      toast('保存失敗: ' + (json.error || '不明'), true);
    }
  } catch(e) {
    toast('通信エラー: ' + e.message, true);
  }
}

/* ===== スライドショー設定 ===== */
let _boardCfg = { slideshow_enabled: false, slideshow_interval: 10 };

function _setSsBtn(enabled) {
  const btn = document.getElementById('ss_enabled');
  if (!btn) return;
  btn.dataset.enabled = enabled ? '1' : '0';
  btn.querySelector('.ss-dot').style.background = enabled ? '#4caf50' : '#aaa';
  btn.querySelector('.ss-label').textContent     = enabled ? '有効' : '無効';
  btn.style.background  = enabled ? 'rgba(76,175,80,0.12)' : 'var(--surface2)';
  btn.style.borderColor = enabled ? '#4caf50'               : 'var(--border)';
  btn.style.color       = enabled ? '#2e7d32'               : 'var(--text-dim)';
}

function toggleSsBtn() {
  const btn = document.getElementById('ss_enabled');
  if (btn) _setSsBtn(btn.dataset.enabled !== '1');
}

async function loadBoardCfg() {
  try {
    const r = await fetch(`${BASE_URL}/api/boards.php?board=${BOARD_KEY}`);
    const cfg = await r.json();
    _boardCfg = cfg;
    _setSsBtn(!!cfg.slideshow_enabled);
    document.getElementById('ss_interval').value = cfg.slideshow_interval || 10;
  } catch(e) {}
}

function openSlideSettings() {
  if (_boardCfg.name) document.getElementById('ss_name').value = _boardCfg.name;
  document.getElementById('ss_width').value    = _boardCfg.width  || 1800;
  document.getElementById('ss_height').value   = _boardCfg.height || 900;
  _setSsBtn(!!_boardCfg.slideshow_enabled);
  document.getElementById('ss_interval').value = _boardCfg.slideshow_interval || 10;
  const m = document.getElementById('slideModal');
  m.style.display = 'flex';
}

function closeSlideSettings() {
  document.getElementById('slideModal').style.display = 'none';
}

async function saveSlideSettings() {
  const name     = document.getElementById('ss_name')?.value?.trim() || _boardCfg.name || '安全資格者掲示板';
  const width    = parseInt(document.getElementById('ss_width').value)    || 1800;
  const height   = parseInt(document.getElementById('ss_height').value)   || 900;
  const enabled  = document.getElementById('ss_enabled').dataset.enabled === '1';
  const interval = parseInt(document.getElementById('ss_interval').value) || 10;
  try {
    await fetch(`${BASE_URL}/api/boards.php?board=${BOARD_KEY}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, width, height, slideshow_enabled: enabled, slideshow_interval: interval }),
    });
    _boardCfg.name               = name;
    _boardCfg.width              = width;
    _boardCfg.height             = height;
    _boardCfg.slideshow_enabled  = enabled;
    _boardCfg.slideshow_interval = interval;
    closeSlideSettings();
    toast('設定を保存しました');
  } catch(e) {
    toast('保存失敗: ' + e.message, true);
  }
}

/* ===== マスターデータ ===== */
let masterJobTypes  = [];
let masterQualItems = [];

async function loadMasters() {
  try {
    const [rj, rq] = await Promise.all([
      fetch(`${BASE_URL}/api/masters.php?board=${BOARD_KEY}&type=job_types`),
      fetch(`${BASE_URL}/api/masters.php?board=${BOARD_KEY}&type=qual_masters`),
    ]);
    const dj = await rj.json();
    const dq = await rq.json();
    masterJobTypes  = (dj.items || []).map(i => i.name);
    masterQualItems = (dq.items || []).map(i => i.name);
  } catch(e) {}
}

function openMasterSettings() {
  renderMasterList('job',  masterJobTypes);
  renderMasterList('qual', masterQualItems);
  document.getElementById('masterModal').classList.add('open');
  switchMasterTab('job');
}

function closeMasterSettings() {
  document.getElementById('masterModal').classList.remove('open');
}

function switchMasterTab(tab) {
  document.querySelectorAll('.master-tab').forEach((el, i) => {
    el.classList.toggle('active', (i === 0 && tab === 'job') || (i === 1 && tab === 'qual'));
  });
  document.getElementById('masterPanelJob') .classList.toggle('active', tab === 'job');
  document.getElementById('masterPanelQual').classList.toggle('active', tab === 'qual');
}

function renderMasterList(tab, items) {
  const listId = tab === 'job' ? 'masterJobList' : 'masterQualList';
  const list   = document.getElementById(listId);
  list.innerHTML = '';
  items.forEach((name, i) => {
    const row = document.createElement('div');
    row.className = 'master-item-row';
    row.innerHTML = `
      <input type="text" value="${eh(name)}" placeholder="名前を入力"
        oninput="masterGetItems('${tab}')[${i}] = this.value">
      <button class="del-btn" onclick="masterRemoveItem('${tab}', ${i})">✕</button>`;
    list.appendChild(row);
  });
}

function masterGetItems(tab) {
  return tab === 'job' ? masterJobTypes : masterQualItems;
}

function addMasterItem(tab) {
  const items  = masterGetItems(tab);
  const listId = tab === 'job' ? 'masterJobList' : 'masterQualList';
  items.push('');
  const i    = items.length - 1;
  const list = document.getElementById(listId);
  const row  = document.createElement('div');
  row.className = 'master-item-row';
  row.innerHTML = `
    <input type="text" placeholder="名前を入力"
      oninput="masterGetItems('${tab}')[${i}] = this.value">
    <button class="del-btn" onclick="masterRemoveItem('${tab}', ${i})">✕</button>`;
  list.appendChild(row);
  row.querySelector('input').focus();
}

function masterRemoveItem(tab, i) {
  masterGetItems(tab).splice(i, 1);
  renderMasterList(tab, masterGetItems(tab));
}

async function saveMasterSettings() {
  const collectItems = tab => {
    const listId = tab === 'job' ? 'masterJobList' : 'masterQualList';
    return [...document.getElementById(listId).querySelectorAll('input')]
      .map(el => el.value.trim())
      .filter(v => v !== '')
      .map(name => ({ name }));
  };
  const jobItems  = collectItems('job');
  const qualItems = collectItems('qual');
  try {
    await Promise.all([
      fetch(`${BASE_URL}/api/masters.php?board=${BOARD_KEY}&type=job_types`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items: jobItems }),
      }),
      fetch(`${BASE_URL}/api/masters.php?board=${BOARD_KEY}&type=qual_masters`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items: qualItems }),
      }),
    ]);
    masterJobTypes  = jobItems.map(i => i.name);
    masterQualItems = qualItems.map(i => i.name);
    toast('マスターを保存しました ✔');
    if (selectedIdx !== null) renderEditor();
  } catch(e) {
    toast('保存失敗: ' + e.message, true);
  }
}

/* ===== 初期描画 ===== */
loadBoardCfg();
loadMasters().then(() => renderGrid());
</script>
</body>
</html>
