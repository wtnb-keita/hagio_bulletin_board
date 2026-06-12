<?php
/**
 * マイグレーション: マルチページ・スライドショー機能追加
 * ブラウザで一度だけアクセスして実行してください。
 */
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

$pdo     = getPDO();
$results = [];

function tryAlter(PDO $pdo, string $sql, string $label, array &$results): void {
    try {
        $pdo->exec($sql);
        $results[] = "✓ $label";
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate column') !== false || stripos($msg, '1060') !== false) {
            $results[] = "- $label (already exists)";
        } else {
            $results[] = "✗ $label: $msg";
        }
    }
}

// panels.page_number
tryAlter($pdo,
    'ALTER TABLE panels ADD COLUMN page_number INT NOT NULL DEFAULT 1',
    'panels.page_number', $results
);

// staff.page_number
tryAlter($pdo,
    'ALTER TABLE staff ADD COLUMN page_number INT NOT NULL DEFAULT 1',
    'staff.page_number', $results
);

// boards.slideshow_enabled
tryAlter($pdo,
    'ALTER TABLE boards ADD COLUMN slideshow_enabled TINYINT(1) NOT NULL DEFAULT 0',
    'boards.slideshow_enabled', $results
);

// boards.slideshow_interval
tryAlter($pdo,
    'ALTER TABLE boards ADD COLUMN slideshow_interval INT NOT NULL DEFAULT 10',
    'boards.slideshow_interval', $results
);

// boards.grid_cols
tryAlter($pdo,
    'ALTER TABLE boards ADD COLUMN grid_cols INT NOT NULL DEFAULT 5',
    'boards.grid_cols', $results
);

// boards.grid_rows
tryAlter($pdo,
    'ALTER TABLE boards ADD COLUMN grid_rows INT NOT NULL DEFAULT 2',
    'boards.grid_rows', $results
);

// panel_accident.initial_days
tryAlter($pdo,
    'ALTER TABLE panel_accident ADD COLUMN initial_days INT NOT NULL DEFAULT 0',
    'panel_accident.initial_days', $results
);

// board_pages テーブル
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS board_pages (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        board_key   VARCHAR(64)  NOT NULL,
        page_number INT          NOT NULL DEFAULT 1,
        page_name   VARCHAR(255) NOT NULL DEFAULT '',
        sort_order  INT          NOT NULL DEFAULT 0,
        UNIQUE KEY uk_board_page (board_key, page_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = '✓ board_pages テーブル作成';
} catch (Throwable $e) {
    $results[] = '✗ board_pages: ' . $e->getMessage();
}

// 既存ボードにデフォルトページを挿入
foreach (['safety_board_1', 'safety_board_2', 'staff_board'] as $bk) {
    try {
        $pdo->prepare(
            "INSERT IGNORE INTO board_pages (board_key, page_number, page_name, sort_order) VALUES (?, 1, 'ページ 1', 0)"
        )->execute([$bk]);
        $results[] = "✓ $bk: デフォルトページ挿入";
    } catch (Throwable $e) {
        $results[] = "✗ $bk: " . $e->getMessage();
    }
}

echo "=== マイグレーション結果 ===\n\n";
foreach ($results as $r) {
    echo $r . "\n";
}
echo "\n完了しました。このファイルは削除しても構いません。\n";
