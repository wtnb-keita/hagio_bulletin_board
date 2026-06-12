<?php
require_once __DIR__ . '/db.php';

// CORS プリフライト
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(null, 204);
}

$method    = $_SERVER['REQUEST_METHOD'];
$boardKey  = $_GET['board'] ?? 'safety_board_1';

// ---- GET: バージョン確認 ----
if ($method === 'GET' && ($_GET['action'] ?? '') === 'version') {
    $file = __DIR__ . '/board_versions.json';
    $versions = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    jsonResponse(['version' => $versions[$boardKey] ?? 0]);
}

// ---- GET: パネル一覧取得 ----
if ($method === 'GET') {
    $pdo = getPDO();

    $panels = $pdo->prepare(
        'SELECT * FROM panels WHERE board_key = ? ORDER BY sort_order, id'
    );
    $panels->execute([$boardKey]);
    $rows = $panels->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $uid  = $row['panel_uid'];
        $type = $row['type'];

        $panel = [
            'id'     => $uid,
            'type'   => $type,
            'title'  => $row['title'],
            'x'      => (int)$row['pos_x'],
            'y'      => (int)$row['pos_y'],
            'width'  => (int)$row['width'],
            'height' => (int)$row['height'],
            'page'   => isset($row['page_number']) ? (int)$row['page_number'] : 1,
            'content'=> [],
        ];

        switch ($type) {
            case 'media':
                $s = $pdo->prepare('SELECT * FROM panel_media WHERE panel_uid=? AND board_key=?');
                $s->execute([$uid, $boardKey]);
                $d = $s->fetch();
                if ($d) {
                    $panel['content'] = [
                        'fileName'  => $d['file_name'],
                        'fileType'  => $d['file_type'],
                        'filePath'  => $d['file_path'] ? uploadUrlBase() . basename($d['file_path']) : '',
                        'label'     => $d['label_text'] ?? '',
                    ];
                }
                break;

            case 'text':
                $s = $pdo->prepare('SELECT * FROM panel_text WHERE panel_uid=? AND board_key=?');
                $s->execute([$uid, $boardKey]);
                $d = $s->fetch();
                if ($d) {
                    $panel['content'] = [
                        'text'     => $d['content'] ?? '',
                        'vertical' => (bool)$d['vertical'],
                        'fontSize' => isset($d['font_size']) ? (int)$d['font_size'] : 14,
                    ];
                }
                break;

            case 'accident':
                $s = $pdo->prepare('SELECT * FROM panel_accident WHERE panel_uid=? AND board_key=?');
                $s->execute([$uid, $boardKey]);
                $d = $s->fetch();
                if ($d) {
                    $panel['content'] = [
                        'targetDays'  => (int)$d['target_days'],
                        'startDate'   => $d['start_date'],
                        'initialDays' => isset($d['initial_days']) ? (int)$d['initial_days'] : 0,
                    ];
                }
                break;

            case 'notice':
                $s = $pdo->prepare(
                    'SELECT * FROM notice_items WHERE panel_uid=? AND board_key=? ORDER BY sort_order, id'
                );
                $s->execute([$uid, $boardKey]);
                $items = $s->fetchAll();
                $panel['content'] = [
                    'notices' => array_map(fn($n) => [
                        'id'        => (int)$n['id'],
                        'title'     => $n['title'],
                        'level'     => (int)$n['level'],
                        'startDate' => $n['start_date'] ?? '',
                        'endDate'   => $n['end_date'] ?? '',
                        'text'      => $n['content'] ?? '',
                    ], $items),
                ];
                break;

            case 'disaster':
                $s = $pdo->prepare('SELECT content FROM panel_text WHERE panel_uid=? AND board_key=?');
                $s->execute([$uid, $boardKey]);
                $d = $s->fetch();
                if ($d && $d['content']) {
                    $decoded = json_decode($d['content'], true);
                    $panel['content'] = is_array($decoded) ? $decoded : ['items'=>[], 'slideshowEnabled'=>false, 'slideshowInterval'=>5];
                } else {
                    $panel['content'] = ['items'=>[], 'slideshowEnabled'=>false, 'slideshowInterval'=>5];
                }
                // filePath を URL に変換
                foreach ($panel['content']['items'] ?? [] as &$item) {
                    if (!empty($item['filePath']) && !str_starts_with($item['filePath'], 'http') && !str_starts_with($item['filePath'], '/')) {
                        $item['filePath'] = uploadUrlBase() . basename($item['filePath']);
                    }
                }
                unset($item);
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

    jsonResponse(['panels' => $result]);
}

// ---- POST: パネル一括保存 ----
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !isset($body['panels'])) {
        errorResponse('Invalid request body');
    }

    $pdo = getPDO();
    $pdo->beginTransaction();

    try {
        // 既存パネルUID一覧
        $existing = $pdo->prepare('SELECT panel_uid FROM panels WHERE board_key=?');
        $existing->execute([$boardKey]);
        $existingUids = array_column($existing->fetchAll(), 'panel_uid');

        $incomingUids = array_column($body['panels'], 'id');

        // 削除されたパネル
        foreach ($existingUids as $uid) {
            if (!in_array($uid, $incomingUids)) {
                deletePanel($pdo, $uid, $boardKey);
            }
        }

        // upsert
        foreach ($body['panels'] as $i => $p) {
            $uid  = $p['id'];
            $type = $p['type'];

            $pdo->prepare(
                'INSERT INTO panels (board_key, panel_uid, type, title, pos_x, pos_y, width, height, sort_order, page_number)
                 VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   type=VALUES(type), title=VALUES(title),
                   pos_x=VALUES(pos_x), pos_y=VALUES(pos_y),
                   width=VALUES(width), height=VALUES(height),
                   sort_order=VALUES(sort_order),
                   page_number=VALUES(page_number)'
            )->execute([
                $boardKey, $uid, $type,
                $p['title'] ?? '',
                $p['x'] ?? 0, $p['y'] ?? 0,
                $p['width'] ?? 300, $p['height'] ?? 200,
                $i,
                $p['page'] ?? 1,
            ]);

            $c = $p['content'] ?? [];
            switch ($type) {
                case 'media':
                    $pdo->prepare(
                        'INSERT INTO panel_media (panel_uid, board_key, file_name, file_type, file_path, label_text)
                         VALUES (?,?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE
                           file_name=VALUES(file_name), file_type=VALUES(file_type),
                           file_path=VALUES(file_path), label_text=VALUES(label_text)'
                    )->execute([
                        $uid, $boardKey,
                        $c['fileName'] ?? '',
                        $c['fileType'] ?? '',
                        $c['filePath'] ?? '',
                        $c['label']    ?? '',
                    ]);
                    break;

                case 'text':
                    $pdo->prepare(
                        'INSERT INTO panel_text (panel_uid, board_key, content, vertical, font_size)
                         VALUES (?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE content=VALUES(content), vertical=VALUES(vertical), font_size=VALUES(font_size)'
                    )->execute([
                        $uid, $boardKey,
                        $c['text']     ?? '',
                        ($c['vertical'] ?? false) ? 1 : 0,
                        $c['fontSize'] ?? 14,
                    ]);
                    break;

                case 'accident':
                    $pdo->prepare(
                        'INSERT INTO panel_accident (panel_uid, board_key, target_days, start_date, initial_days)
                         VALUES (?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE target_days=VALUES(target_days), start_date=VALUES(start_date), initial_days=VALUES(initial_days)'
                    )->execute([
                        $uid, $boardKey,
                        $c['targetDays']  ?? 1500,
                        $c['startDate']   ?? date('Y-m-d'),
                        $c['initialDays'] ?? 0,
                    ]);
                    break;

                case 'disaster':
                    $pdo->prepare(
                        'INSERT INTO panel_text (panel_uid, board_key, content, vertical)
                         VALUES (?,?,?,0)
                         ON DUPLICATE KEY UPDATE content=VALUES(content)'
                    )->execute([
                        $uid, $boardKey,
                        json_encode([
                            'items'             => $c['items']             ?? [],
                            'slideshowEnabled'  => $c['slideshowEnabled']  ?? false,
                            'slideshowInterval' => $c['slideshowInterval'] ?? 5,
                        ], JSON_UNESCAPED_UNICODE),
                    ]);
                    break;

                case 'responsible':
                    $pdo->prepare(
                        'INSERT INTO panel_responsible (panel_uid, board_key, role_name, person_name, font_size)
                         VALUES (?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE role_name=VALUES(role_name), person_name=VALUES(person_name), font_size=VALUES(font_size)'
                    )->execute([
                        $uid, $boardKey,
                        $c['role']     ?? '化学物質管理者',
                        $c['name']     ?? '',
                        $c['fontSize'] ?? 40,
                    ]);
                    break;

                case 'notice':
                    $pdo->prepare(
                        'INSERT IGNORE INTO panel_notice (panel_uid, board_key) VALUES (?,?)'
                    )->execute([$uid, $boardKey]);

                    // 告知アイテム: 全削除→再挿入
                    $pdo->prepare('DELETE FROM notice_items WHERE panel_uid=? AND board_key=?')
                        ->execute([$uid, $boardKey]);

                    foreach ($c['notices'] ?? [] as $ni => $n) {
                        $pdo->prepare(
                            'INSERT INTO notice_items (panel_uid, board_key, title, level, start_date, end_date, content, sort_order)
                             VALUES (?,?,?,?,?,?,?,?)'
                        )->execute([
                            $uid, $boardKey,
                            $n['title']     ?? '',
                            $n['level']     ?? 1,
                            $n['startDate'] ?: null,
                            $n['endDate']   ?: null,
                            $n['text']      ?? '',
                            $ni,
                        ]);
                    }
                    break;
            }
        }

        $pdo->commit();

        // バージョンタイムスタンプを更新（ビュー側の自動更新用）
        $vFile = __DIR__ . '/board_versions.json';
        $versions = file_exists($vFile) ? json_decode(file_get_contents($vFile), true) : [];
        $versions[$boardKey] = time();
        file_put_contents($vFile, json_encode($versions));

        jsonResponse(['ok' => true]);

    } catch (Throwable $e) {
        $pdo->rollBack();
        errorResponse('DB error: ' . $e->getMessage(), 500);
    }
}

errorResponse('Method not allowed', 405);

// ---- ヘルパー ----
function deletePanel(PDO $pdo, string $uid, string $boardKey): void {
    foreach (['panel_media','panel_text','panel_accident','panel_notice','panel_responsible'] as $tbl) {
        $pdo->prepare("DELETE FROM {$tbl} WHERE panel_uid=? AND board_key=?")->execute([$uid, $boardKey]);
    }
    $pdo->prepare('DELETE FROM notice_items WHERE panel_uid=? AND board_key=?')->execute([$uid, $boardKey]);
    $pdo->prepare('DELETE FROM panels WHERE panel_uid=? AND board_key=?')->execute([$uid, $boardKey]);
}
