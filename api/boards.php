<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(null, 204);
}

$boardKey = $_GET['board'] ?? 'safety_board_1';
$method   = $_SERVER['REQUEST_METHOD'];

// ---- GET: ボード設定取得 ----
if ($method === 'GET') {
    $default = [
        'boardKey'           => $boardKey,
        'name'               => '掲示板',
        'width'              => 1800,
        'height'             => 900,
        'slideshow_enabled'  => false,
        'slideshow_interval' => 10,
        'grid_cols'          => 5,
        'grid_rows'          => 2,
    ];
    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare('SELECT * FROM boards WHERE board_key = ?');
        $stmt->execute([$boardKey]);
        $row  = $stmt->fetch();

        if (!$row) {
            jsonResponse($default);
        }

        jsonResponse([
            'boardKey'           => $row['board_key'],
            'name'               => $row['name'],
            'width'              => (int)$row['width'],
            'height'             => (int)$row['height'],
            'slideshow_enabled'  => (bool)($row['slideshow_enabled'] ?? false),
            'slideshow_interval' => (int)($row['slideshow_interval'] ?? 10),
            'grid_cols'          => (int)($row['grid_cols'] ?? 5),
            'grid_rows'          => (int)($row['grid_rows'] ?? 2),
        ]);
    } catch (Throwable $e) {
        jsonResponse($default);
    }
}

// ---- POST: ボード設定更新 ----
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    $name     = trim($body['name']   ?? '');
    $width    = (int)($body['width']  ?? 1800);
    $height   = (int)($body['height'] ?? 900);
    $ssEnabled  = !empty($body['slideshow_enabled'])  ? 1 : 0;
    $ssInterval = max(3, (int)($body['slideshow_interval'] ?? 10));
    // grid_cols / grid_rows 未指定時は現在値を維持
    $curCols = 5; $curRows = 2;
    try {
        $cur = getPDO()->prepare('SELECT grid_cols, grid_rows FROM boards WHERE board_key = ?');
        $cur->execute([$boardKey]);
        if ($c = $cur->fetch()) { $curCols = (int)$c['grid_cols']; $curRows = (int)$c['grid_rows']; }
    } catch (Throwable $e) {}
    $gridCols = min(12, max(1, (int)($body['grid_cols'] ?? $curCols)));
    $gridRows = min(8,  max(1, (int)($body['grid_rows'] ?? $curRows)));

    if ($width  < 400 || $width  > 7680) errorResponse('width は 400〜7680 の範囲で指定してください');
    if ($height < 200 || $height > 4320) errorResponse('height は 200〜4320 の範囲で指定してください');
    if ($name === '') errorResponse('name は必須です');

    $pdo = getPDO();
    $pdo->prepare(
        'INSERT INTO boards (board_key, name, width, height, slideshow_enabled, slideshow_interval, grid_cols, grid_rows)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name=VALUES(name), width=VALUES(width), height=VALUES(height),
           slideshow_enabled=VALUES(slideshow_enabled), slideshow_interval=VALUES(slideshow_interval),
           grid_cols=VALUES(grid_cols), grid_rows=VALUES(grid_rows)'
    )->execute([$boardKey, $name, $width, $height, $ssEnabled, $ssInterval, $gridCols, $gridRows]);

    jsonResponse(['ok' => true, 'width' => $width, 'height' => $height, 'name' => $name,
                  'slideshow_enabled' => (bool)$ssEnabled, 'slideshow_interval' => $ssInterval,
                  'grid_cols' => $gridCols, 'grid_rows' => $gridRows]);
}

errorResponse('Method not allowed', 405);
