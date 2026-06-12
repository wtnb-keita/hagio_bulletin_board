<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(null, 204);
}

$method   = $_SERVER['REQUEST_METHOD'];
$boardKey = $_GET['board'] ?? 'staff_board';
$type     = $_GET['type']  ?? ''; // 'job_types' or 'qual_masters'

$tableMap = [
    'job_types'    => 'staff_job_types',
    'qual_masters' => 'staff_qual_masters',
];

if (!isset($tableMap[$type])) {
    errorResponse('type パラメータが不正です（job_types / qual_masters）');
}

$table = $tableMap[$type];

// ---- GET: 一覧取得 ----
if ($method === 'GET') {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("SELECT id, name, sort_order FROM {$table} WHERE board_key = ? ORDER BY sort_order, id");
    $stmt->execute([$boardKey]);
    $rows = $stmt->fetchAll();
    jsonResponse(['items' => array_map(fn($r) => [
        'id'        => (int)$r['id'],
        'name'      => $r['name'],
        'sortOrder' => (int)$r['sort_order'],
    ], $rows)]);
}

// ---- POST: 一括保存 ----
if ($method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $items = $body['items'] ?? null;

    if (!is_array($items)) {
        errorResponse('items が必要です');
    }

    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM {$table} WHERE board_key = ?")->execute([$boardKey]);

        foreach ($items as $i => $item) {
            $name = trim($item['name'] ?? '');
            if ($name === '') continue;
            $pdo->prepare("INSERT INTO {$table} (board_key, name, sort_order) VALUES (?, ?, ?)")
                ->execute([$boardKey, $name, $i]);
        }

        $pdo->commit();
        jsonResponse(['ok' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        errorResponse('保存エラー: ' . $e->getMessage());
    }
}

errorResponse('Method not allowed', 405);
