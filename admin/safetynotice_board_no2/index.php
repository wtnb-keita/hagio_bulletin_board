<?php
require_once __DIR__ . '/../../api/db.php';

const BOARD_KEY = 'safety_board_2';

$boardConfig = ['name'=>'安全掲示板 No.2', 'width'=>1800, 'height'=>900];
try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM boards WHERE board_key = ?');
    $stmt->execute([BOARD_KEY]);
    $row  = $stmt->fetch();
    if ($row) $boardConfig = ['name'=>$row['name'], 'width'=>(int)$row['width'], 'height'=>(int)$row['height']];
} catch (Throwable $e) {}

$panels = [];
$dbError = '';
try {
    $panels = fetchPanels(BOARD_KEY);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$maxId = 0;
foreach ($panels as $p) {
    $n = (int)preg_replace('/\D/', '', $p['id']);
    if ($n > $maxId) $maxId = $n;
}
$nextId = $maxId + 1;

$panelsJson = json_encode($panels, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>管理画面 - 安全掲示板 No.2</title>
<link rel="stylesheet" href="../../assets/css/common.css">
<link rel="stylesheet" href="../../assets/css/admin.css">
<style>
#addPanelModal .modal { width: 480px; }

.type-selector {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 16px;
}
.type-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 14px 10px;
    background: var(--surface2);
    border: 2px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    color: var(--text);
    font-family: inherit;
    font-size: 13px;
}
.type-btn:hover    { border-color: var(--accent); }
.type-btn.selected { border-color: var(--accent); background: rgba(233,69,96,0.1); }
.type-btn.type-disaster.selected { border-color: #f44336; background: rgba(244,67,54,0.1); }
.type-btn .icon    { font-size: 26px; }
.type-btn .label   { font-weight: bold; }
.type-btn .desc    { font-size: 11px; color: var(--text-dim); text-align: center; }

#addPanelModal .modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}
</style>
</head>
<body>

<header class="admin-header">
    <h1><?= htmlspecialchars($boardConfig['name']) ?> 管理画面</h1>
    <button class="subtitle board-size-btn" onclick="Admin.openBoardSettings()"
            title="クリックしてボード設定を変更"
            style="background:none;border:none;color:rgba(255,255,255,.7);cursor:pointer;font-size:13px;
                   padding:2px 6px;border-radius:3px;transition:background .15s;"
            onmouseover="this.style.background='rgba(255,255,255,.15)'"
            onmouseout="this.style.background='none'">
      <?= $boardConfig['width'] ?> × <?= $boardConfig['height'] ?>px ビュー ✎
    </button>
    <div class="header-actions">
        <a href="layout.php" class="btn btn-secondary">📐 レイアウト編集</a>
        <a href="<?= rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') ?>/uploads/index.php" class="btn btn-secondary">📁 アップロード管理</a>
        <button class="btn btn-accent2" onclick="Admin.openViewBoard()">🖥 ビュー画面を開く</button>
        <button class="btn btn-success" onclick="Admin.applyEdits();Admin.saveAll()">💾 保存</button>
    </div>
</header>

<nav class="admin-nav">
    <a href="<?= rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') ?>/safetynotice_board_no1/index.php">安全掲示板 No.1</a>
    <a href="<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/index.php" class="active">安全掲示板 No.2</a>
</nav>

<div class="admin-body">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>パネル一覧</h2>
            <button class="btn btn-primary btn-sm" onclick="Admin.openAddPanel()">＋ 追加</button>
        </div>
        <div class="panel-list" id="panelList"></div>
    </aside>

    <main class="editor" id="editor">
        <div class="editor-empty">← パネルを選択して編集</div>
    </main>
</div>

<div class="toast" id="toast"></div>

<!-- ========== パネル追加モーダル ========== -->
<div class="modal-overlay" id="addPanelModal">
    <div class="modal">
        <div class="modal-header">
            <h2>パネルを追加</h2>
            <button class="btn btn-secondary btn-sm" onclick="Admin.closeAddPanel()" style="margin-left:auto">✕</button>
        </div>
        <p style="font-size:12px;color:var(--text-dim);margin-bottom:10px">パネルの種別を選択してください</p>
        <div class="type-selector">
            <button class="type-btn" data-type="media" onclick="Admin.selectType(this)">
                <span class="icon">🖼</span>
                <span class="label">メディア</span>
                <span class="desc">画像・PDFを<br>表示するパネル</span>
            </button>
            <button class="type-btn" data-type="text" onclick="Admin.selectType(this)">
                <span class="icon">📝</span>
                <span class="label">テキスト</span>
                <span class="desc">自由なテキストを<br>表示するパネル</span>
            </button>
            <button class="type-btn" data-type="accident" onclick="Admin.selectType(this)">
                <span class="icon">🏆</span>
                <span class="label">無災害記録</span>
                <span class="desc">経過日数カウンターを<br>表示するパネル</span>
            </button>
            <button class="type-btn" data-type="notice" onclick="Admin.selectType(this)">
                <span class="icon">📢</span>
                <span class="label">告知</span>
                <span class="desc">複数の告知を<br>一覧表示するパネル</span>
            </button>
            <button class="type-btn type-disaster" data-type="disaster" onclick="Admin.selectType(this)" style="grid-column:1/-1">
                <span class="icon">🚨</span>
                <span class="label">災害速報</span>
                <span class="desc">○○会災害速報を表示するパネル（画像・PDF・テキスト複数対応・スライドショー）</span>
            </button>
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>タイトル</label>
            <input type="text" id="addPanelTitle" placeholder="パネルのタイトル（後から変更できます）">
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Admin.closeAddPanel()">キャンセル</button>
            <button class="btn btn-primary" id="addPanelConfirm" onclick="Admin.confirmAddPanel()" disabled>追加する</button>
        </div>
    </div>
</div>

<!-- ========== レイアウトプレビューモーダル ========== -->
<div class="modal-overlay" id="layoutModal">
    <div class="modal">
        <div class="modal-header">
            <h2>レイアウト確認（1/2 縮小表示）</h2>
            <button class="btn btn-secondary btn-sm" onclick="Admin.closeLayoutPreview()" style="margin-left:16px">✕ 閉じる</button>
        </div>
        <div style="position:relative;width:900px;height:450px;">
            <div class="layout-preview" id="layoutPreview"
                 style="transform:scale(0.5);transform-origin:top left;width:1800px;height:900px;position:absolute;top:0;left:0;">
            </div>
        </div>
    </div>
</div>

<?php if ($dbError): ?>
<div style="position:fixed;top:60px;left:50%;transform:translateX(-50%);
            background:#c62828;color:#fff;padding:10px 20px;border-radius:6px;
            z-index:9999;font-size:13px;max-width:600px;word-break:break-all;">
    ⚠ DB接続エラー: <?= htmlspecialchars($dbError) ?>
</div>
<?php endif; ?>

<!-- ========== ボード設定モーダル ========== -->
<div class="modal-overlay" id="boardSettingsModal">
    <div class="modal" style="width:400px">
        <div class="modal-header">
            <h2>⚙ ボード設定</h2>
            <button class="btn btn-secondary btn-sm" onclick="Admin.closeBoardSettings()" style="margin-left:auto">✕</button>
        </div>
        <div class="form-group" style="margin-bottom:12px">
            <label>掲示板名</label>
            <input type="text" id="bs_name" value="<?= htmlspecialchars($boardConfig['name']) ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>幅 (px)</label>
                <input type="number" id="bs_width" value="<?= $boardConfig['width'] ?>" min="400" max="7680" step="1">
            </div>
            <div class="form-group">
                <label>高さ (px)</label>
                <input type="number" id="bs_height" value="<?= $boardConfig['height'] ?>" min="200" max="4320" step="1">
            </div>
        </div>
        <div style="margin-top:6px;margin-bottom:14px">
            <p style="font-size:11px;color:var(--text-dim);margin-bottom:6px">よく使うサイズ:</p>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                <?php
                $presets = [
                    ['1920×1080 (FHD)', 1920, 1080],
                    ['1800×900',        1800,  900],
                    ['3840×2160 (4K)',  3840, 2160],
                    ['1280×720 (HD)',   1280,  720],
                ];
                foreach ($presets as [$label, $w, $h]):
                ?>
                <button class="btn btn-secondary btn-sm"
                        onclick="document.getElementById('bs_width').value=<?= $w ?>;document.getElementById('bs_height').value=<?= $h ?>">
                    <?= $label ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;border-top:1px solid var(--border);padding-top:12px">
            <button class="btn btn-secondary" onclick="Admin.closeBoardSettings()">キャンセル</button>
            <button class="btn btn-success" onclick="Admin.saveBoardSettings()">💾 保存</button>
        </div>
    </div>
</div>

<!-- ========== アップロードライブラリモーダル ========== -->
<div class="modal-overlay" id="uploadLibModal">
    <div class="modal" style="width:860px;max-width:95vw;">
        <div class="modal-header">
            <h2>📁 アップロード済みから選択</h2>
            <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
                <label class="btn btn-primary btn-sm" style="cursor:pointer">
                    ＋ 新規アップロード
                    <input type="file" id="libUploadInput" accept="image/*,application/pdf" multiple style="display:none">
                </label>
                <button class="btn btn-secondary btn-sm" onclick="Admin.closeUploadLibrary()">✕ 閉じる</button>
            </div>
        </div>
        <div class="file-drop" id="libDropZone" style="margin-bottom:12px;padding:10px 16px;">
            ここにファイルをドロップして追加
        </div>
        <div id="libGrid" style="
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(130px,1fr));
            gap:10px;
            max-height:420px;
            overflow-y:auto;
            padding:4px;
        "></div>
        <p style="font-size:11px;color:var(--text-dim);margin-top:10px">ファイルをクリックしてパネルに設定します</p>
    </div>
</div>

<script src="../../assets/js/api.js"></script>
<script src="../../assets/js/panel-render.js"></script>
<script>
const INITIAL_PANELS  = <?= $panelsJson ?>;
const INITIAL_NEXT_ID = <?= $nextId ?>;
const BASE_URL        = '<?= rtrim(str_replace('\\', '/', str_replace(rtrim($_SERVER['DOCUMENT_ROOT'],'/\\'), '', dirname(dirname(dirname(__FILE__))))), '/') ?>';
const ADMIN_BOARD_KEY = 'safety_board_2';
const ADMIN_VIEW_URL  = '/view_board/safetynotice_board_no2/index.php';
</script>
<script src="../../assets/js/admin.js"></script>
</body>
</html>
