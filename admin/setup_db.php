<?php
require_once __DIR__ . '/../api/db.php';

$results = [];
$success = true;

$sqls = [
    'boards テーブル作成' => "
        CREATE TABLE IF NOT EXISTS boards (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            board_key   VARCHAR(64) NOT NULL UNIQUE,
            name        VARCHAR(255) NOT NULL,
            width       INT NOT NULL DEFAULT 1800,
            height      INT NOT NULL DEFAULT 900,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",
    'panels テーブル作成' => "
        CREATE TABLE IF NOT EXISTS panels (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            board_key   VARCHAR(64) NOT NULL,
            panel_uid   VARCHAR(64) NOT NULL,
            type        ENUM('media','text','accident','notice','disaster') NOT NULL,
            title       VARCHAR(255) DEFAULT '',
            pos_x       INT NOT NULL DEFAULT 0,
            pos_y       INT NOT NULL DEFAULT 0,
            width       INT NOT NULL DEFAULT 300,
            height      INT NOT NULL DEFAULT 200,
            sort_order  INT NOT NULL DEFAULT 0,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_board_uid (board_key, panel_uid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",
    'panel_media テーブル作成' => "
        CREATE TABLE IF NOT EXISTS panel_media (
            panel_uid   VARCHAR(64) PRIMARY KEY,
            board_key   VARCHAR(64) NOT NULL,
            file_name   VARCHAR(255) DEFAULT '',
            file_type   VARCHAR(64)  DEFAULT '',
            file_path   VARCHAR(512) DEFAULT '',
            label_text  TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",
    'panel_text テーブル作成' => "
        CREATE TABLE IF NOT EXISTS panel_text (
            panel_uid   VARCHAR(64) PRIMARY KEY,
            board_key   VARCHAR(64) NOT NULL,
            content     TEXT DEFAULT NULL,
            vertical    TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",
    'panel_accident テーブル作成' => "
        CREATE TABLE IF NOT EXISTS panel_accident (
            panel_uid   VARCHAR(64) PRIMARY KEY,
            board_key   VARCHAR(64) NOT NULL,
            target_days INT NOT NULL DEFAULT 1500,
            start_date  DATE NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",
    'panel_notice テーブル作成' => "
        CREATE TABLE IF NOT EXISTS panel_notice (
            panel_uid   VARCHAR(64) PRIMARY KEY,
            board_key   VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",
    'notice_items テーブル作成' => "
        CREATE TABLE IF NOT EXISTS notice_items (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            panel_uid   VARCHAR(64) NOT NULL,
            board_key   VARCHAR(64) NOT NULL,
            title       VARCHAR(255) DEFAULT '',
            level       TINYINT NOT NULL DEFAULT 1,
            start_date  DATE DEFAULT NULL,
            end_date    DATE DEFAULT NULL,
            content     TEXT DEFAULT NULL,
            sort_order  INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",
    'panels.type に disaster を追加' => "
        ALTER TABLE panels MODIFY COLUMN type ENUM('media','text','accident','notice','disaster') NOT NULL
    ",
    'boards 初期データ投入 No.1' => "
        INSERT IGNORE INTO boards (board_key, name, width, height)
        VALUES ('safety_board_1', '安全掲示板 No.1', 1800, 900)
    ",
    'boards 初期データ投入 No.2' => "
        INSERT IGNORE INTO boards (board_key, name, width, height)
        VALUES ('safety_board_2', '安全掲示板 No.2', 1800, 900)
    ",
];

try {
    $pdo = getPDO();
    foreach ($sqls as $label => $sql) {
        try {
            $pdo->exec(trim($sql));
            $results[] = ['label' => $label, 'ok' => true, 'msg' => 'OK'];
        } catch (Throwable $e) {
            $results[] = ['label' => $label, 'ok' => false, 'msg' => $e->getMessage()];
            $success = false;
        }
    }
} catch (Throwable $e) {
    $results[] = ['label' => 'DB接続', 'ok' => false, 'msg' => $e->getMessage()];
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>DBセットアップ</title>
<style>
  body { font-family: 'Meiryo', sans-serif; background: #f4f6f8; color: #1a1a1a; padding: 30px; }
  h1   { color: #2e7d32; margin-bottom: 20px; font-size: 20px; }
  table { border-collapse: collapse; width: 100%; max-width: 700px; }
  th, td { padding: 8px 12px; border: 1px solid #d0d5db; font-size: 13px; }
  th { background: #e8f5e9; color: #2e7d32; }
  .ok  { color: #2e7d32; font-weight: bold; }
  .err { color: #c62828; font-weight: bold; }
  .result-box {
    margin-top: 20px;
    padding: 12px 18px;
    border-radius: 6px;
    font-size: 15px;
    font-weight: bold;
  }
  .result-ok  { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
  .result-err { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
  .btn { display: inline-block; margin-top: 16px; padding: 8px 18px; background: #2e7d32; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px; }
</style>
</head>
<body>
<h1>🛠 DBセットアップ</h1>

<table>
  <tr><th>処理</th><th>結果</th><th>メッセージ</th></tr>
  <?php foreach ($results as $r): ?>
  <tr>
    <td><?= htmlspecialchars($r['label']) ?></td>
    <td class="<?= $r['ok'] ? 'ok' : 'err' ?>"><?= $r['ok'] ? '✓ 成功' : '✗ 失敗' ?></td>
    <td><?= htmlspecialchars($r['msg']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<div class="result-box <?= $success ? 'result-ok' : 'result-err' ?>">
  <?= $success ? '✓ セットアップ完了' : '✗ エラーが発生しました。上のメッセージを確認してください。' ?>
</div>

<?php if ($success): ?>
  <a href="/admin/safetynotice_board_no1/index.php" class="btn">→ 管理画面へ</a>
<?php endif; ?>
</body>
</html>
