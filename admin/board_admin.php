<?php
require_once __DIR__ . '/../api/db.php';

$boardKey = $_GET['board'] ?? 'safety_board_1';

$BOARD_CFGS = [
    'safety_board_1' => [
        'default_name' => '安全掲示板 No.1',
        'view_url'     => '/view_board/safetynotice_board_no1/index.php',
    ],
    'safety_board_2' => [
        'default_name' => '安全掲示板 No.2',
        'view_url'     => '/view_board/safetynotice_board_no2/index.php',
    ],
];
if (!array_key_exists($boardKey, $BOARD_CFGS)) $boardKey = 'safety_board_1';
$bCfg = $BOARD_CFGS[$boardKey];

// adminディレクトリのベースURL
$adminBase = rtrim(str_replace('\\', '/', str_replace(rtrim($_SERVER['DOCUMENT_ROOT'],'/\\'), '', __DIR__)), '/');

// ボード設定取得
$boardConfig = ['name' => $bCfg['default_name'], 'width' => 1800, 'height' => 900];
try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM boards WHERE board_key = ?');
    $stmt->execute([$boardKey]);
    $row  = $stmt->fetch();
    if ($row) $boardConfig = ['name' => $row['name'], 'width' => (int)$row['width'], 'height' => (int)$row['height']];
} catch (Throwable $e) {}

$panels = [];
$dbError = '';
try {
    $panels = fetchPanels($boardKey);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$pages = fetchPages($boardKey);
$pagesJson = json_encode($pages, JSON_UNESCAPED_UNICODE);

$maxId = 0;
foreach ($panels as $p) {
    $n = (int)preg_replace('/\D/', '', $p['id']);
    if ($n > $maxId) $maxId = $n;
}
$nextId = $maxId + 1;

$panelsJson = json_encode($panels, JSON_UNESCAPED_UNICODE);

// アプリルートURL（JS側で BASE_URL として使う）
$appBase = rtrim(str_replace('\\', '/', str_replace(rtrim($_SERVER['DOCUMENT_ROOT'],'/\\'), '', dirname(__DIR__))), '/');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>管理画面 - <?= htmlspecialchars($boardConfig['name']) ?></title>
<link rel="stylesheet" href="../assets/css/common.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
/* ---- パネル追加モーダル ---- */
#addPanelModal .modal { width: 480px; }

/* ---- ページタブ（ナビ直下・フルワイド） ---- */
.page-tabs-bar {
    display: flex;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 4px;
    padding: 0 16px;
    background: var(--surface2);
    border-bottom: 2px solid var(--accent);
    min-height: 36px;
}
.page-tab {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 6px 14px;
    border-radius: 6px 6px 0 0;
    border: 1px solid var(--border);
    border-bottom: none;
    background: var(--surface);
    color: var(--text-dim);
    font-size: 13px;
    cursor: pointer;
    font-family: inherit;
    transition: background .15s, color .15s;
    margin-top: 4px;
}
.page-tab:hover { background: var(--bg); color: var(--text); }
.page-tab.active {
    background: var(--bg);
    color: var(--text);
    border-color: var(--accent);
    border-bottom: 2px solid var(--bg);
    margin-bottom: -2px;
    font-weight: bold;
}
.page-tab-del {
    font-size: 10px;
    opacity: .4;
    margin-left: 4px;
    padding: 1px 3px;
    border-radius: 3px;
    line-height: 1;
}
.page-tab-del:hover { opacity: 1; color: var(--accent); background: rgba(0,0,0,.06); }
.page-tab-add {
    padding: 6px 10px;
    border-radius: 6px 6px 0 0;
    border: 1px dashed var(--border);
    border-bottom: none;
    background: none;
    color: var(--text-dim);
    font-size: 13px;
    cursor: pointer;
    font-family: inherit;
    transition: color .15s;
    margin-top: 4px;
}
.page-tab-add:hover { color: var(--accent); }

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
        <a href="layout_editor.php?board=<?= htmlspecialchars($boardKey) ?>" class="btn btn-secondary">📐 レイアウト編集</a>
        <a href="<?= $adminBase ?>/uploads/index.php" class="btn btn-secondary">📁 アップロード管理</a>
        <button class="btn btn-secondary" onclick="Admin.openBoardSettings()">🖼 スライドショー設定</button>
        <button class="btn btn-accent2" onclick="Admin.openViewBoard()">🖥 ビュー画面を開く</button>
        <button class="btn btn-success" onclick="Admin.applyEdits();Admin.saveAll()">💾 保存</button>
    </div>
</header>

<nav class="admin-nav">
    <a href="board_admin.php?board=safety_board_1"
       <?= $boardKey === 'safety_board_1' ? 'class="active"' : '' ?>>安全掲示板 No.1</a>
    <a href="board_admin.php?board=safety_board_2"
       <?= $boardKey === 'safety_board_2' ? 'class="active"' : '' ?>>安全掲示板 No.2</a>
    <a href="staff_board/index.php">安全資格者掲示板</a>
</nav>

<!-- ページタブ（ナビ直下） -->
<div class="page-tabs-bar" id="pageTabs"></div>

<div class="admin-body">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>パネル一覧</h2>
            <button class="btn btn-primary btn-sm" onclick="Admin.openAddPanel()">＋ 追加</button>
            <button class="btn btn-secondary btn-sm" onclick="Admin.openTemplateModal()">📋 テンプレート</button>
        </div>
        <div class="panel-list" id="panelList"></div>
    </aside>

    <main class="editor" id="editor">
        <div class="editor-empty">← パネルを選択して編集</div>
    </main>
</div>

<!-- トースト -->
<div class="toast" id="toast"></div>

<!-- ========== テンプレートモーダル ========== -->
<div class="modal-overlay" id="templateModal">
    <div class="modal" style="width:560px;max-height:80vh;display:flex;flex-direction:column">
        <div class="modal-header">
            <h2>📋 テンプレートから選択</h2>
            <button class="btn btn-secondary btn-sm" onclick="Admin.closeTemplateModal()" style="margin-left:auto">✕</button>
        </div>
        <div id="templateList" style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px"></div>
    </div>
</div>

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
            <button class="type-btn" data-type="responsible" onclick="Admin.selectType(this)">
                <span class="icon">🪧</span>
                <span class="label">責任者掲示</span>
                <span class="desc">管理者名を縦書きで<br>表示するパネル</span>
            </button>
            <button class="type-btn type-disaster" data-type="disaster" onclick="Admin.selectType(this)">
                <span class="icon">🚨</span>
                <span class="label">災害速報</span>
                <span class="desc">○○会災害速報を表示するパネル<br>（画像・PDF・テキスト複数対応）</span>
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

<!-- DBエラー表示 -->
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
                <?php foreach ([['1920×1080 (FHD)',1920,1080],['1800×900',1800,900],['3840×2160 (4K)',3840,2160],['1280×720 (HD)',1280,720]] as [$label,$w,$h]): ?>
                <button class="btn btn-secondary btn-sm"
                        onclick="document.getElementById('bs_width').value=<?= $w ?>;document.getElementById('bs_height').value=<?= $h ?>">
                    <?= $label ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="border-top:1px solid var(--border);margin-top:12px;padding-top:12px">
            <p style="font-size:12px;font-weight:bold;margin-bottom:10px;color:var(--text);">🖼 スライドショー設定</p>
            <div style="margin-bottom:12px">
                <button id="bs_slideshow"
                        data-enabled="0"
                        onclick="Admin.toggleSlideshowBtn()"
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
                <input type="number" id="bs_interval" value="10" min="3" max="300" step="1">
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;border-top:1px solid var(--border);padding-top:12px;margin-top:12px">
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

<script src="../assets/js/api.js"></script>
<script src="../assets/js/panel-render.js"></script>
<script>
const INITIAL_PANELS  = <?= $panelsJson ?>;
const INITIAL_NEXT_ID = <?= $nextId ?>;
const INITIAL_PAGES   = <?= $pagesJson ?>;
const BASE_URL        = '<?= $appBase ?>';
const ADMIN_BOARD_KEY = '<?= $boardKey ?>';
const ADMIN_VIEW_URL  = '<?= $bCfg['view_url'] ?>';
const ADMIN_BOARD_W   = <?= $boardConfig['width'] ?>;
const ADMIN_BOARD_H   = <?= $boardConfig['height'] ?>;
</script>
<script src="../assets/js/admin.js"></script>
</body>
</html>
