<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(null, 204);
}

$boardKey = $_GET['board'] ?? 'safety_board_1';
$method   = $_SERVER['REQUEST_METHOD'];

// ---- GET: ボード設定取得 ----
if ($method === 'GET') {
    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM boards WHERE board_key = ?');
    $stmt->execute([$boardKey]);
    $row  = $stmt->fetch();

    if (!$row) {
        // レコードがなければデフォルト値を返す
        jsonResponse([
            'boardKey' => $boardKey,
            'name'     => '安全掲示板 No.1',
            'width'    => 1800,
            'height'   => 900,
        ]);
    }

    jsonResponse([
        'boardKey' => $row['board_key'],
        'name'     => $row['name'],
        'width'    => (int)$row['width'],
        'height'   => (int)$row['height'],
    ]);
}

// ---- POST: ボード設定更新 ----
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    $name   = trim($body['name']   ?? '');
    $width  = (int)($body['width']  ?? 1800);
    $height = (int)($body['height'] ?? 900);

    if ($width  < 400 || $width  > 7680) errorResponse('width は 400〜7680 の範囲で指定してください');
    if ($height < 200 || $height > 4320) errorResponse('height は 200〜4320 の範囲で指定してください');
    if ($name === '') errorResponse('name は必須です');

    $pdo = getPDO();
    $pdo->prepare(
        'INSERT INTO boards (board_key, name, width, height)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name=VALUES(name), width=VALUES(width), height=VALUES(height)'
    )->execute([$boardKey, $name, $width, $height]);

    jsonResponse(['ok' => true, 'width' => $width, 'height' => $height, 'name' => $name]);
}

errorResponse('Method not allowed', 405);
