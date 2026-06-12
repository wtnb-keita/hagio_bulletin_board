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
.staff-card.is-dragging   { opacity: .4; }
.staff-card.drag-over     { border-color: #4caf50; box-shadow: 0 0 0 3px rgba(76,175,80,.35); }
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

/* ===== レイアウトプレビュー（view画面のカードを忠実に再現） ===== */
#pvBoard {
  background: #e8edf3;
  transform-origin: top left;
  overflow: hidden;
  font-family: 'Meiryo', 'Yu Gothic', sans-serif;
}
#pvHeader {
  height: 52px; background: #1a4f72; border-bottom: 4px solid #e94560;
  display: flex; align-items: center; justify-content: center; padding: 0 24px; gap: 10px;
}
#pvHeader .pv-cross { position: relative; width: 28px; height: 28px; flex-shrink: 0; }
#pvHeader .pv-cross::before, #pvHeader .pv-cross::after { content: ''; position: absolute; background: #2ecc40; border-radius: 2px; }
#pvHeader .pv-cross::before { width: 34%; height: 100%; left: 33%; top: 0; }
#pvHeader .pv-cross::after  { width: 100%; height: 34%; left: 0; top: 33%; }
#pvHeader h1 { font-size: 24px; font-weight: bold; color: #fff; letter-spacing: .06em; }

#pvSlideshow .staff-slide { display: none; }
#pvSlideshow .staff-slide.active { display: grid; }
#pvSlideshow .card {
  background: #fff; border: 3px solid #f5c518; border-radius: 12px; overflow: hidden;
  display: flex; flex-direction: column; box-shadow: 0 1px 4px rgba(0,0,0,.08);
}
#pvSlideshow .card-empty { background: rgba(255,255,255,.35); border: 1px dashed #b8cfe0; border-radius: 12px; }
#pvSlideshow .card-top { display: flex; gap: 10px; padding: 12px; border-bottom: 1px solid #eee; background: #fffde7; min-height: 130px; }
#pvSlideshow .card-photo { width: 100px; height: 100px; flex-shrink: 0; border-radius: 8px; overflow: hidden; background: #d0e0ef; }
#pvSlideshow .card-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
#pvSlideshow .card-photo-none { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 36px; color: #8aaec8; }
#pvSlideshow .card-top-info { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 0; }
#pvSlideshow .badge-anzen { align-self: center; display: flex; align-items: center; gap: 8px; color: #2e7d32; font-size: 20px; font-weight: bold; white-space: nowrap; }
#pvSlideshow .safety-cross { position: relative; width: 32px; height: 32px; flex-shrink: 0; }
#pvSlideshow .safety-cross::before, #pvSlideshow .safety-cross::after { content: ''; position: absolute; background: #2ecc40; border-radius: 2px; }
#pvSlideshow .safety-cross::before { width: 34%; height: 100%; left: 33%; top: 0; }
#pvSlideshow .safety-cross::after  { width: 100%; height: 34%; left: 0; top: 33%; }
#pvSlideshow .card-body { flex: 1; display: flex; flex-direction: column; padding: 8px 10px 6px; min-height: 0; }
#pvSlideshow .card-basic-info { display: flex; flex-direction: column; margin-bottom: 6px; flex-shrink: 0; }
#pvSlideshow .card-basic-info > div { display: flex; gap: 20px; }
#pvSlideshow .card-basic-info > div:first-child { margin-bottom: 4px; }
#pvSlideshow .card-basic-info > div:first-child .card-info-label { flex: 1; }
#pvSlideshow .card-basic-info > div:first-child .card-info-label:last-child { flex: 0 0 auto; }
#pvSlideshow .card-basic-info > div:last-child .card-name { flex: 1; }
#pvSlideshow .card-basic-info > div:last-child .card-blood { flex: 0 0 auto; }
#pvSlideshow .card-info-label { font-size: 10px; color: #666; margin-bottom: 1px; }
#pvSlideshow .card-name { font-size: 18px; font-weight: 700; color: #1a2e40; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#pvSlideshow .card-blood { font-size: 16px; font-weight: 700; color: #c62828; margin-left: auto; white-space: nowrap; }
#pvSlideshow .card-job-type { margin-top: 16px; font-size: 16px; font-weight: 600; color: #1a4f72; background: #dceeff; border-radius: 4px; padding: 4px 12px; white-space: nowrap; }
#pvSlideshow .card-qual-label { font-size: 13px; font-weight: 700; margin-bottom: 6px; flex-shrink: 0; }
#pvSlideshow .card-quals { flex: 1; display: flex; flex-wrap: wrap; align-content: flex-start; gap: 6px; overflow: hidden; }
#pvSlideshow .qual { padding: 4px 10px; border-radius: 20px; background: #eef6ff; border: 1px solid #c5daf7; color: #1a4f72; font-size: 13px; white-space: nowrap; line-height: 1.5; }

/* プレビュー内のドラッグ用セル */
#pvSlideshow .pv-cell { position: relative; cursor: grab; }
#pvSlideshow .pv-cell.is-dragging { opacity: .35; }
#pvSlideshow .pv-cell.drag-over::after {
  content: ''; position: absolute; inset: 0; border: 4px solid #4caf50;
  border-radius: 12px; pointer-events: none; box-shadow: 0 0 0 3px rgba(76,175,80,.3);
}
#pvSlideshow .card-empty.drag-over {
  border: 4px solid #4caf50; background: rgba(76,175,80,.15);
  box-shadow: 0 0 0 3px rgba(76,175,80,.3);
}
.pv-dot {
  min-width: 30px; height: 24px; padding: 0 8px; border-radius: 12px;
  background: rgba(180,180,180,.5); border: 2px solid var(--border);
  color: #fff; font-size: 12px; font-weight: bold; cursor: pointer;
  transition: transform .12s, background .12s;
}
.pv-dot.active { background: var(--accent); border-color: var(--accent); }
.pv-dot-hover  { background: #4caf50; border-color: #4caf50; transform: scale(1.18); }

/* ドラッグ中のページ送りエッジ（画面端の当たり判定） */
.pv-edge {
  position: absolute; top: 0; bottom: 0; width: 72px;
  display: flex; align-items: center; justify-content: center;
  font-size: 44px; color: #fff; font-weight: bold;
  opacity: 0; pointer-events: none; z-index: 20;
  cursor: pointer; user-select: none;
  transition: opacity .15s, background .15s;
}
.pv-edge-l { left: 0;  background: linear-gradient(to right, rgba(76,175,80,.55), rgba(76,175,80,0)); }
.pv-edge-r { right: 0; background: linear-gradient(to left,  rgba(76,175,80,.55), rgba(76,175,80,0)); }
#pvStage.pv-dragging .pv-edge:not(.pv-edge-disabled) { opacity: .9; pointer-events: auto; }
.pv-edge.pv-edge-active { opacity: 1 !important; }
.pv-edge-l.pv-edge-active { background: linear-gradient(to right, rgba(76,175,80,.95), rgba(76,175,80,.1)); }
.pv-edge-r.pv-edge-active { background: linear-gradient(to left,  rgba(76,175,80,.95), rgba(76,175,80,.1)); }
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
    <button class="btn btn-secondary" onclick="openLayoutPreview()">🔲 レイアウト編集</button>
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
    <p style="font-size:12px;font-weight:bold;margin-bottom:10px;color:var(--text);">🔲 レイアウト設定</p>
    <div class="form-row">
      <div class="form-group">
        <label>列数</label>
        <input type="number" id="ss_cols" value="5" min="1" max="12" step="1">
      </div>
      <div class="form-group">
        <label>行数</label>
        <input type="number" id="ss_rows" value="2" min="1" max="8" step="1">
      </div>
    </div>
    <p style="font-size:11px;color:var(--text-dim);margin-top:4px;">
      カードの縦横比は維持されたまま、列×行に合わせて拡大縮小されます。
    </p>
    </div>
    <div style="border-top:1px solid var(--border);margin-top:12px;padding-top:12px">
    <p style="font-size:12px;font-weight:bold;margin-bottom:10px;color:var(--text);">🖼 スライドショー設定</p>
    <p style="font-size:12px;color:var(--text-dim);margin-bottom:12px">
      スタッフ数が「列数×行数」を超えると自動的に複数ページに分かれます。
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

<!-- ===== レイアウトプレビューモーダル ===== -->
<div class="modal-overlay" id="previewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;align-items:center;justify-content:center;">
  <div class="modal" style="width:min(94vw,1100px);max-height:94vh;display:flex;flex-direction:column;background:var(--surface);border-radius:8px;padding:18px;">
    <div class="modal-header" style="display:flex;align-items:center;margin-bottom:12px;">
      <h2>🔲 レイアウトプレビュー</h2>
      <span style="font-size:12px;color:var(--text-dim);margin-left:12px;">ビュー画面と同じ表示です。カードはドラッグで並び替え（ドラッグ中に左右の端 ◁ ▷ へ寄せるとページ移動）できます。</span>
      <button class="btn btn-secondary btn-sm" onclick="closeLayoutPreview()" style="margin-left:auto">✕ 閉じる</button>
    </div>

    <!-- コントロール -->
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:20px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--border);">
      <div style="display:flex;align-items:center;gap:8px;">
        <span style="font-size:13px;color:var(--text);font-weight:bold;">列数</span>
        <button class="btn btn-secondary btn-sm" onclick="pvStep('cols',-1)">−</button>
        <span id="pvColsLabel" style="min-width:28px;text-align:center;font-size:16px;font-weight:bold;color:var(--text);">5</span>
        <button class="btn btn-secondary btn-sm" onclick="pvStep('cols',1)">＋</button>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span style="font-size:13px;color:var(--text);font-weight:bold;">行数</span>
        <button class="btn btn-secondary btn-sm" onclick="pvStep('rows',-1)">−</button>
        <span id="pvRowsLabel" style="min-width:28px;text-align:center;font-size:16px;font-weight:bold;color:var(--text);">2</span>
        <button class="btn btn-secondary btn-sm" onclick="pvStep('rows',1)">＋</button>
      </div>
      <span id="pvInfo" style="font-size:12px;color:var(--text-dim);"></span>
    </div>

    <!-- プレビュー領域 -->
    <div id="pvStage" style="position:relative;flex:1;overflow:auto;background:#111;border-radius:6px;display:flex;align-items:center;justify-content:center;padding:16px;min-height:0;">
      <div id="pvViewport" style="position:relative;">
        <div id="pvBoard">
          <div id="pvHeader">
            <span class="pv-cross"></span>
            <h1 id="pvTitle">安全資格者掲示板</h1>
          </div>
          <div id="pvSlideshow"></div>
        </div>
      </div>
      <!-- ドラッグ中だけ表示されるページ送りエッジ -->
      <div id="pvEdgeL" class="pv-edge pv-edge-l">◁</div>
      <div id="pvEdgeR" class="pv-edge pv-edge-r">▷</div>
    </div>

    <!-- ページナビ -->
    <div id="pvNav" style="display:flex;justify-content:center;gap:8px;margin-top:10px;min-height:14px;"></div>

    <div style="display:flex;justify-content:flex-end;gap:8px;border-top:1px solid var(--border);padding-top:12px;margin-top:12px;">
      <button class="btn btn-secondary" onclick="closeLayoutPreview()">キャンセル</button>
      <button class="btn btn-success" onclick="savePreviewLayout()">💾 この設定で保存</button>
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

    // ドラッグ&ドロップで並び替え
    card.draggable = true;
    card.addEventListener('dragstart', e => {
      _dragIdx = i;
      card.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    card.addEventListener('dragend', () => {
      _dragIdx = null;
      document.querySelectorAll('.staff-card').forEach(c => c.classList.remove('is-dragging', 'drag-over'));
    });
    card.addEventListener('dragover', e => {
      if (_dragIdx === null || _dragIdx === i) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      card.classList.add('drag-over');
    });
    card.addEventListener('dragleave', () => card.classList.remove('drag-over'));
    card.addEventListener('drop', e => {
      e.preventDefault();
      if (_dragIdx === null || _dragIdx === i) return;
      moveStaff(_dragIdx, i);
    });

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

/* ===== 並び替え ===== */
let _dragIdx = null;

async function moveStaff(from, to) {
  flushEditor();
  const sel = selectedIdx !== null ? staffList[selectedIdx] : null;
  const [moved] = staffList.splice(from, 1);
  staffList.splice(to, 0, moved);
  if (sel) selectedIdx = staffList.indexOf(sel);
  renderGrid();
  renderEditor();
  await saveAll(true, '並び順を保存しました ✔');
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
async function saveAll(silent = false, silentMsg = '削除しました') {
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
        toast(silentMsg);
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

/* ===== レイアウトプレビュー ===== */
/* view画面と同じ基準寸法（縦横比固定の基準） */
const PV_BASE_CW = 346;
const PV_BASE_CH = 409;

let pvCols    = 5;
let pvRows    = 2;
let pvCurPage = 0;
let _pvDragIdx = null;
let _pvPageCount = 1;
let _pvEdgeTimer = null;   // 画面端ホバー中の連続ページ送りタイマー
let _pvEdgeDir   = 0;      // -1:前ページ / +1:次ページ
let _pvEdgeBound = false;

/* ドラッグ中、画面端のエッジに重ねたら一定間隔でページ送り */
function pvSetupEdgeDnD() {
  if (_pvEdgeBound) return;
  _pvEdgeBound = true;
  const bind = (id, dir) => {
    const edge = document.getElementById(id);
    edge.addEventListener('dragover', e => {
      if (_pvDragIdx === null) return;
      const target = pvCurPage + dir;
      if (target < 0 || target >= _pvPageCount) { pvClearEdge(); return; }
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      if (_pvEdgeDir === dir && _pvEdgeTimer) return;  // 既に同方向で稼働中
      pvClearEdge();
      _pvEdgeDir = dir;
      edge.classList.add('pv-edge-active');
      _pvEdgeTimer = setInterval(() => {
        const next = pvCurPage + _pvEdgeDir;
        if (next < 0 || next >= _pvPageCount) { pvClearEdge(); return; }
        pvGoPage(next);  // 再描画してもエッジ要素・_pvDragIdx は保持される
      }, 650);
    });
    edge.addEventListener('dragleave', () => pvClearEdge());
  };
  bind('pvEdgeL', -1);
  bind('pvEdgeR', +1);
}

function pvClearEdge() {
  if (_pvEdgeTimer) { clearInterval(_pvEdgeTimer); _pvEdgeTimer = null; }
  _pvEdgeDir = 0;
  document.querySelectorAll('.pv-edge.pv-edge-active').forEach(el => el.classList.remove('pv-edge-active'));
}

/* view画面の buildCard と同等のカード生成 */
function pvBuildCard(s) {
  const photoHtml = s.photoPath
    ? `<img src="${eh(s.photoPath)}" alt="${eh(s.name)}">`
    : `<div class="card-photo-none">👤</div>`;
  const quals = (s.qualifications || []).filter(q => (q || '').trim() !== '')
    .map(q => `<span class="qual">${eh(q)}</span>`).join('');
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

function openLayoutPreview() {
  flushEditor();
  pvCols = Math.min(12, Math.max(1, parseInt(_boardCfg.grid_cols) || 5));
  pvRows = Math.min(8,  Math.max(1, parseInt(_boardCfg.grid_rows) || 2));
  pvCurPage = 0;
  if (_boardCfg.name) document.getElementById('pvTitle').textContent = _boardCfg.name;
  document.getElementById('previewModal').style.display = 'flex';
  pvSetupEdgeDnD();
  // モーダル表示後にステージ寸法が確定するので次フレームで描画
  requestAnimationFrame(pvRender);
}

function closeLayoutPreview() {
  document.getElementById('previewModal').style.display = 'none';
}

function pvStep(which, delta) {
  if (which === 'cols') pvCols = Math.min(12, Math.max(1, pvCols + delta));
  else                  pvRows = Math.min(8,  Math.max(1, pvRows + delta));
  pvCurPage = 0;
  pvRender();
}

function pvRender() {
  const boardW  = _boardCfg.width  || 1800;
  const boardH  = _boardCfg.height || 900;
  const perPage = pvCols * pvRows;

  const valid = staffList.filter(s => (s.name || '').trim() !== '');
  const pageCount = Math.max(1, Math.ceil(valid.length / perPage));
  if (pvCurPage >= pageCount) pvCurPage = 0;

  document.getElementById('pvColsLabel').textContent = pvCols;
  document.getElementById('pvRowsLabel').textContent = pvRows;
  document.getElementById('pvInfo').textContent =
    `${valid.length}人 / 1ページ${perPage}枠 / 全${pageCount}ページ`;

  // ボード本体を実寸で組み、ステージに収まるよう全体を縮小表示
  const board = document.getElementById('pvBoard');
  board.style.width  = boardW + 'px';
  board.style.height = boardH + 'px';

  const ss     = document.getElementById('pvSlideshow');
  const slideH = boardH - 52;
  ss.style.height = slideH + 'px';
  ss.innerHTML = '';

  // 縦横比固定でカードサイズを算出（view画面と同じロジック）
  const gap    = 10;
  const availW = boardW - 28 - gap * (pvCols - 1);
  const availH = slideH  - 20 - gap * (pvRows - 1);
  const scale  = Math.min(availW / pvCols / PV_BASE_CW, availH / pvRows / PV_BASE_CH);
  const cardW  = PV_BASE_CW * scale;
  const cardH  = PV_BASE_CH * scale;

  const pages = [];
  for (let i = 0; i < valid.length; i += perPage) pages.push(valid.slice(i, i + perPage));
  if (pages.length === 0) pages.push([]);

  pages.forEach((pageStaff, pi) => {
    const slide = document.createElement('div');
    slide.className = `staff-slide${pi === pvCurPage ? ' active' : ''}`;
    slide.style.cssText = `
      height:${slideH}px;
      grid-template-columns:repeat(${pvCols},${cardW}px);
      grid-template-rows:repeat(${pvRows},${cardH}px);
      gap:${gap}px; padding:10px 14px;
      justify-content:center; align-content:center;
    `;
    for (let i = 0; i < perPage; i++) {
      const cell = document.createElement('div');
      cell.style.cssText = `width:${cardW}px;height:${cardH}px;`;
      const member = pageStaff[i];
      if (member) {
        cell.className = 'pv-cell';
        const globalIdx = staffList.indexOf(member);
        cell.dataset.idx = globalIdx;
        cell.draggable = true;
        const card = pvBuildCard(member);
        card.style.cssText = `width:${PV_BASE_CW}px;height:${PV_BASE_CH}px;transform:scale(${scale});transform-origin:top left;`;
        cell.appendChild(card);
        pvAttachDrag(cell, globalIdx);
      } else {
        cell.className = 'card-empty';
        pvAttachEmptyDrop(cell, pi);
      }
      slide.appendChild(cell);
    }
    ss.appendChild(slide);
  });

  // ステージに収まる表示倍率
  const stage = document.getElementById('pvStage');
  const dispScale = Math.min(
    (stage.clientWidth  - 32) / boardW,
    (stage.clientHeight - 32) / boardH,
    1
  );
  board.style.transform = `scale(${dispScale})`;
  const vp = document.getElementById('pvViewport');
  vp.style.width  = (boardW * dispScale) + 'px';
  vp.style.height = (boardH * dispScale) + 'px';

  // ページナビ（クリックでも移動できるインジケーター）
  const nav = document.getElementById('pvNav');
  if (pages.length <= 1) {
    nav.innerHTML = '';
  } else {
    nav.innerHTML = pages.map((_, i) =>
      `<button class="pv-dot${i === pvCurPage ? ' active' : ''}" data-page="${i}" onclick="pvGoPage(${i})">${i + 1}</button>`
    ).join('');
  }

  // ページ送りエッジの有効/無効（端のページでは方向を消す）
  _pvPageCount = pages.length;
  document.getElementById('pvEdgeL').classList.toggle('pv-edge-disabled', pvCurPage <= 0);
  document.getElementById('pvEdgeR').classList.toggle('pv-edge-disabled', pvCurPage >= pages.length - 1);
}

function pvGoPage(idx) {
  pvCurPage = idx;
  pvRender();
}

function pvAttachDrag(cell, idx) {
  cell.addEventListener('dragstart', e => {
    _pvDragIdx = idx;
    cell.classList.add('is-dragging');
    document.getElementById('pvStage').classList.add('pv-dragging');
    e.dataTransfer.effectAllowed = 'move';
  });
  cell.addEventListener('dragend', () => {
    _pvDragIdx = null;
    pvClearEdge();
    document.getElementById('pvStage').classList.remove('pv-dragging');
    document.querySelectorAll('#pvSlideshow .pv-cell').forEach(c => c.classList.remove('is-dragging', 'drag-over'));
  });
  cell.addEventListener('dragover', e => {
    if (_pvDragIdx === null || _pvDragIdx === idx) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    cell.classList.add('drag-over');
  });
  cell.addEventListener('dragleave', () => cell.classList.remove('drag-over'));
  cell.addEventListener('drop', e => {
    e.preventDefault();
    if (_pvDragIdx === null || _pvDragIdx === idx) return;
    const from = _pvDragIdx;
    const sel = selectedIdx !== null ? staffList[selectedIdx] : null;
    const [moved] = staffList.splice(from, 1);
    staffList.splice(idx, 0, moved);
    if (sel) selectedIdx = staffList.indexOf(sel);
    renderGrid();
    pvRender();
  });
}

/* 空き枠へのドロップ＝そのページの末尾へ移動 */
function pvAttachEmptyDrop(cell, page) {
  cell.addEventListener('dragover', e => {
    if (_pvDragIdx === null) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    cell.classList.add('drag-over');
  });
  cell.addEventListener('dragleave', () => cell.classList.remove('drag-over'));
  cell.addEventListener('drop', e => {
    e.preventDefault();
    cell.classList.remove('drag-over');
    if (_pvDragIdx === null) return;
    pvMoveToPageEnd(_pvDragIdx, page);
  });
}

function pvMoveToPageEnd(from, page) {
  const sel     = selectedIdx !== null ? staffList[selectedIdx] : null;
  const moved   = staffList[from];
  const perPage = pvCols * pvRows;
  const valid   = staffList.filter(s => (s.name || '').trim() !== '');
  const members = valid.slice(page * perPage, page * perPage + perPage);
  // このページの「動かす対象以外」の最後のカードをアンカーにして、その直後へ挿入
  let anchor = null;
  for (let k = members.length - 1; k >= 0; k--) {
    if (members[k] !== moved) { anchor = members[k]; break; }
  }
  staffList.splice(from, 1);
  if (anchor) {
    staffList.splice(staffList.indexOf(anchor) + 1, 0, moved);
  } else {
    staffList.push(moved);
  }
  if (sel) selectedIdx = staffList.indexOf(sel);
  renderGrid();
  pvRender();
}

async function savePreviewLayout() {
  // レイアウト設定を保存
  const name     = _boardCfg.name   || '安全資格者掲示板';
  const width    = _boardCfg.width  || 1800;
  const height   = _boardCfg.height || 900;
  const enabled  = !!_boardCfg.slideshow_enabled;
  const interval = _boardCfg.slideshow_interval || 10;
  try {
    await fetch(`${BASE_URL}/api/boards.php?board=${BOARD_KEY}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, width, height, slideshow_enabled: enabled,
                             slideshow_interval: interval, grid_cols: pvCols, grid_rows: pvRows }),
    });
    _boardCfg.grid_cols = pvCols;
    _boardCfg.grid_rows = pvRows;
    // 並び替えも保存
    await saveAll(true, 'レイアウトと並び順を保存しました ✔');
    closeLayoutPreview();
  } catch(e) {
    toast('保存失敗: ' + e.message, true);
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
    document.getElementById('ss_cols').value     = cfg.grid_cols || 5;
    document.getElementById('ss_rows').value     = cfg.grid_rows || 2;
  } catch(e) {}
}

function openSlideSettings() {
  if (_boardCfg.name) document.getElementById('ss_name').value = _boardCfg.name;
  document.getElementById('ss_width').value    = _boardCfg.width  || 1800;
  document.getElementById('ss_height').value   = _boardCfg.height || 900;
  _setSsBtn(!!_boardCfg.slideshow_enabled);
  document.getElementById('ss_interval').value = _boardCfg.slideshow_interval || 10;
  document.getElementById('ss_cols').value     = _boardCfg.grid_cols || 5;
  document.getElementById('ss_rows').value     = _boardCfg.grid_rows || 2;
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
  const cols     = Math.min(12, Math.max(1, parseInt(document.getElementById('ss_cols').value) || 5));
  const rows     = Math.min(8,  Math.max(1, parseInt(document.getElementById('ss_rows').value) || 2));
  try {
    await fetch(`${BASE_URL}/api/boards.php?board=${BOARD_KEY}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, width, height, slideshow_enabled: enabled, slideshow_interval: interval,
                             grid_cols: cols, grid_rows: rows }),
    });
    _boardCfg.name               = name;
    _boardCfg.width              = width;
    _boardCfg.height             = height;
    _boardCfg.slideshow_enabled  = enabled;
    _boardCfg.slideshow_interval = interval;
    _boardCfg.grid_cols          = cols;
    _boardCfg.grid_rows          = rows;
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

/* プレビュー表示中はウィンドウリサイズで再フィット */
window.addEventListener('resize', () => {
  if (document.getElementById('previewModal').style.display === 'flex') pvRender();
});

/* ===== 初期描画 ===== */
loadBoardCfg();
loadMasters().then(() => renderGrid());
</script>
</body>
</html>
