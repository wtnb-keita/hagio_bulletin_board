<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(null, 204);
}

$method   = $_SERVER['REQUEST_METHOD'];
$boardKey = $_GET['board'] ?? 'staff_board';
$action   = $_GET['action'] ?? '';

// ---- GET: 更新タイムスタンプチェック ----
if ($method === 'GET' && isset($_GET['check'])) {
    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT MAX(updated_at) AS ts FROM staff WHERE board_key = ?');
    $stmt->execute([$boardKey]);
    $row  = $stmt->fetch();
    jsonResponse(['ts' => $row['ts'] ?? '']);
}

// ---- GET: スタッフ一覧取得 ----
if ($method === 'GET') {
    $pdo  = getPDO();
    $stmt = $pdo->prepare(
        'SELECT * FROM staff WHERE board_key = ? ORDER BY sort_order, id'
    );
    $stmt->execute([$boardKey]);
    $rows = $stmt->fetchAll();

    $staffList = [];
    foreach ($rows as $row) {
        $qs = $pdo->prepare(
            'SELECT name FROM staff_qualifications WHERE staff_id = ? ORDER BY sort_order, id'
        );
        $qs->execute([$row['id']]);
        $quals = array_column($qs->fetchAll(), 'name');

        $photoPath = '';
        if ($row['photo_path']) {
            $photoPath = uploadUrlBase() . basename($row['photo_path']);
        }

        $staffList[] = [
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'department'     => $row['department'],
            'jobType'        => $row['job_type'] ?? '',
            'photoPath'      => $photoPath,
            'qualifications' => $quals,
            'sortOrder'      => (int)$row['sort_order'],
        ];
    }

    jsonResponse(['staff' => $staffList]);
}

// ---- POST: スタッフ追加・更新 ----
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    // 一括保存
    if (isset($body['staff']) && is_array($body['staff'])) {
        $pdo = getPDO();
        $pdo->beginTransaction();
        try {
            // 既存データ削除
            $pdo->prepare('DELETE FROM staff_qualifications WHERE staff_id IN (SELECT id FROM staff WHERE board_key = ?)')->execute([$boardKey]);
            $pdo->prepare('DELETE FROM staff WHERE board_key = ?')->execute([$boardKey]);

            foreach ($body['staff'] as $i => $s) {
                $name       = trim($s['name'] ?? '');
                $department = trim($s['department'] ?? '');
                $jobType    = trim($s['jobType'] ?? '');
                $photoPath  = trim($s['photoPath'] ?? '');
                $quals      = $s['qualifications'] ?? [];

                if ($name === '') continue;

                // photoPath がURLの場合はファイル名だけ保存
                if ($photoPath && str_contains($photoPath, '/uploads/')) {
                    $photoPath = basename($photoPath);
                }

                $ins = $pdo->prepare(
                    'INSERT INTO staff (board_key, name, department, job_type, photo_path, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([$boardKey, $name, $department, $jobType, $photoPath, $i]);
                $staffId = (int)$pdo->lastInsertId();

                foreach ($quals as $qi => $qname) {
                    $qname = trim($qname);
                    if ($qname === '') continue;
                    $pdo->prepare(
                        'INSERT INTO staff_qualifications (staff_id, name, sort_order) VALUES (?, ?, ?)'
                    )->execute([$staffId, $qname, $qi]);
                }
            }

            $pdo->commit();
            jsonResponse(['ok' => true]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            errorResponse('保存エラー: ' . $e->getMessage());
        }
    }

    errorResponse('パラメータが不正です');
}

// ---- DELETE: スタッフ削除（単体） ----
if ($method === 'DELETE') {
    $body    = json_decode(file_get_contents('php://input'), true);
    $staffId = (int)($body['id'] ?? 0);
    if (!$staffId) errorResponse('id が必要です');

    $pdo = getPDO();
    $pdo->prepare('DELETE FROM staff_qualifications WHERE staff_id = ?')->execute([$staffId]);
    $pdo->prepare('DELETE FROM staff WHERE id = ? AND board_key = ?')->execute([$staffId, $boardKey]);
    jsonResponse(['ok' => true]);
}

errorResponse('Method not allowed', 405);
