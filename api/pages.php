<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') jsonResponse(null, 204);

$boardKey = $_GET['board'] ?? 'safety_board_1';
$method   = $_SERVER['REQUEST_METHOD'];

// ---- GET: ページ一覧 ----
if ($method === 'GET') {
    $default = ['pages' => [['page_number' => 1, 'page_name' => 'ページ 1', 'sort_order' => 0]]];
    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare(
            'SELECT page_number, page_name, sort_order FROM board_pages WHERE board_key = ? ORDER BY sort_order, page_number'
        );
        $stmt->execute([$boardKey]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            jsonResponse($default);
        }

        jsonResponse(['pages' => array_map(fn($r) => [
            'page_number' => (int)$r['page_number'],
            'page_name'   => $r['page_name'],
            'sort_order'  => (int)$r['sort_order'],
        ], $rows)]);
    } catch (Throwable $e) {
        jsonResponse($default);
    }
}

// ---- POST: ページ一括保存 ----
if ($method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $pages = $body['pages'] ?? [];

    if (!is_array($pages) || empty($pages)) {
        errorResponse('pages は空にできません');
    }

    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM board_pages WHERE board_key = ?')->execute([$boardKey]);
        foreach ($pages as $i => $p) {
            $pdo->prepare(
                'INSERT INTO board_pages (board_key, page_number, page_name, sort_order) VALUES (?, ?, ?, ?)'
            )->execute([
                $boardKey,
                (int)($p['page_number'] ?? ($i + 1)),
                $p['page_name'] ?? 'ページ ' . ($i + 1),
                $i,
            ]);
        }
        $pdo->commit();
        jsonResponse(['ok' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        errorResponse('DB error: ' . $e->getMessage(), 500);
    }
}

errorResponse('Method not allowed', 405);
