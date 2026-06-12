<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(null, 204);
}

$method = $_SERVER['REQUEST_METHOD'];

$createSql = 'CREATE TABLE IF NOT EXISTS panel_templates (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    type       VARCHAR(50)  NOT NULL,
    title      VARCHAR(255) NOT NULL DEFAULT \'\',
    content    JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

// ---- GET: テンプレート一覧 ----
if ($method === 'GET') {
    try {
        $pdo = getPDO();
        $pdo->exec($createSql);
        $rows = $pdo->query('SELECT id, name, type, title, content FROM panel_templates ORDER BY id DESC')->fetchAll();
        jsonResponse(['templates' => array_map(fn($r) => [
            'id'      => (int)$r['id'],
            'name'    => $r['name'],
            'type'    => $r['type'],
            'title'   => $r['title'],
            'content' => json_decode($r['content'] ?? 'null', true) ?? [],
        ], $rows)]);
    } catch (Throwable $e) {
        jsonResponse(['templates' => []]);
    }
}

// ---- POST: テンプレート保存 ----
if ($method === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $name    = trim($body['name']  ?? '');
    $type    = trim($body['type']  ?? '');
    $title   = trim($body['title'] ?? '');
    $content = $body['content'] ?? [];

    if ($name === '') errorResponse('name は必須です');
    if ($type === '') errorResponse('type は必須です');

    $pdo = getPDO();
    $pdo->exec($createSql);
    $stmt = $pdo->prepare('INSERT INTO panel_templates (name, type, title, content) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $type, $title, json_encode($content, JSON_UNESCAPED_UNICODE)]);
    jsonResponse(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
}

// ---- DELETE: テンプレート削除 ----
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) errorResponse('id が不正です');
    $pdo = getPDO();
    $pdo->prepare('DELETE FROM panel_templates WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

errorResponse('Method not allowed', 405);
