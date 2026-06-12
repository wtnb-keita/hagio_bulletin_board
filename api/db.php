<?php
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'mysql');
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'hagio_board');
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'hagio');
define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'hagio_pass');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

function uploadUrlBase(): string {
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $appRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $rel = $docRoot ? str_replace($docRoot, '', $appRoot) : '';
    return rtrim(str_replace('\\', '/', $rel), '/') . '/uploads/';
}

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function jsonResponse(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function errorResponse(string $msg, int $status = 400): void {
    jsonResponse(['error' => $msg], $status);
}

/**
 * 指定ボードのページ一覧を配列で返す（管理画面PHP用）
 */
function fetchPages(string $boardKey): array {
    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare(
            'SELECT page_number, page_name, sort_order FROM board_pages WHERE board_key = ? ORDER BY sort_order, page_number'
        );
        $stmt->execute([$boardKey]);
        $rows = $stmt->fetchAll();
        if (!empty($rows)) {
            return array_map(fn($r) => [
                'page_number' => (int)$r['page_number'],
                'page_name'   => $r['page_name'],
                'sort_order'  => (int)$r['sort_order'],
            ], $rows);
        }
    } catch (Throwable $e) { /* 未マイグレーション時は fallback */ }
    return [['page_number' => 1, 'page_name' => 'ページ 1', 'sort_order' => 0]];
}

/**
 * 指定ボードのパネル一覧を配列で返す（管理画面PHP用）
 */
function fetchPanels(string $boardKey): array {
    $pdo = getPDO();

    $stmt = $pdo->prepare(
        'SELECT * FROM panels WHERE board_key = ? ORDER BY sort_order, id'
    );
    $stmt->execute([$boardKey]);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $uid  = $row['panel_uid'];
        $type = $row['type'];
        $panel = [
            'id'      => $uid,
            'type'    => $type,
            'title'   => $row['title'],
            'x'       => (int)$row['pos_x'],
            'y'       => (int)$row['pos_y'],
            'width'   => (int)$row['width'],
            'height'  => (int)$row['height'],
            'page'    => isset($row['page_number']) ? (int)$row['page_number'] : 1,
            'content' => [],
        ];

        switch ($type) {
            case 'media':
                $s = $pdo->prepare('SELECT * FROM panel_media WHERE panel_uid=? AND board_key=?');
                $s->execute([$uid, $boardKey]);
                $d = $s->fetch();
                $panel['content'] = $d ? [
                    'fileName' => $d['file_name'],
                    'fileType' => $d['file_type'],
                    'filePath' => $d['file_path'] ? uploadUrlBase() . basename($d['file_path']) : '',
                    'label'    => $d['label_text'] ?? '',
                ] : ['fileName'=>'','fileType'=>'','filePath'=>'','label'=>''];
                break;

            case 'text':
                $s = $pdo->prepare('SELECT * FROM panel_text WHERE panel_uid=? AND board_key=?');
                $s->execute([$uid, $boardKey]);
                $d = $s->fetch();
                $panel['content'] = $d ? [
                    'text'     => $d['content'] ?? '',
                    'vertical' => (bool)$d['vertical'],
                    'fontSize' => isset($d['font_size']) ? (int)$d['font_size'] : 14,
                ] : ['text'=>'','vertical'=>false,'fontSize'=>14];
                break;

            case 'accident':
                $s = $pdo->prepare('SELECT * FROM panel_accident WHERE panel_uid=? AND board_key=?');
                $s->execute([$uid, $boardKey]);
                $d = $s->fetch();
                $panel['content'] = $d ? [
                    'targetDays'  => (int)$d['target_days'],
                    'startDate'   => $d['start_date'],
                    'initialDays' => isset($d['initial_days']) ? (int)$d['initial_days'] : 0,
                ] : ['targetDays'=>1500,'startDate'=>date('Y-m-d'),'initialDays'=>0];
                break;

            case 'notice':
                $s = $pdo->prepare(
                    'SELECT * FROM notice_items WHERE panel_uid=? AND board_key=? ORDER BY sort_order, id'
                );
                $s->execute([$uid, $boardKey]);
                $items = $s->fetchAll();
                $panel['content'] = ['notices' => array_map(fn($n) => [
                    'id'        => (int)$n['id'],
                    'title'     => $n['title'],
                    'level'     => (int)$n['level'],
                    'startDate' => $n['start_date'] ?? '',
                    'endDate'   => $n['end_date'] ?? '',
                    'text'      => $n['content'] ?? '',
                ], $items)];
                break;

            case 'responsible':
                $s = $pdo->prepare('SELECT * FROM panel_responsible WHERE panel_uid=? AND board_key=?');
                $s->execute([$uid, $boardKey]);
                $d = $s->fetch();
                $panel['content'] = $d ? [
                    'role'     => $d['role_name'],
                    'name'     => $d['person_name'],
                    'fontSize' => isset($d['font_size']) ? (int)$d['font_size'] : 40,
                ] : ['role' => '化学物質管理者', 'name' => '', 'fontSize' => 40];
                break;
        }
        $result[] = $panel;
    }
    return $result;
}
