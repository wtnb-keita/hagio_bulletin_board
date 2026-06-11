<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(null, 204);
}

$method = $_SERVER['REQUEST_METHOD'];

// ---- GET: ファイル一覧 ----
if ($method === 'GET') {
    if (!is_dir(UPLOAD_DIR)) {
        jsonResponse(['files' => []]);
    }

    $files = [];
    foreach (new DirectoryIterator(UPLOAD_DIR) as $f) {
        if ($f->isDot() || $f->isDir()) continue;
        $ext      = strtolower($f->getExtension());
        $allowed  = ['jpg','jpeg','png','gif','webp','pdf'];
        if (!in_array($ext, $allowed)) continue;

        $mimeMap = [
            'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png',  'gif'  => 'image/gif',
            'webp' => 'image/webp', 'pdf'  => 'application/pdf',
        ];
        $files[] = [
            'fileName' => $f->getFilename(),
            'filePath' => uploadUrlBase() . $f->getFilename(),
            'fileType' => $mimeMap[$ext] ?? 'application/octet-stream',
            'fileSize' => $f->getSize(),
            'modified' => $f->getMTime(),
        ];
    }

    // 新しい順
    usort($files, fn($a,$b) => $b['modified'] - $a['modified']);

    jsonResponse(['files' => $files]);
}

// ---- DELETE: ファイル削除 ----
if ($method === 'DELETE') {
    $body     = json_decode(file_get_contents('php://input'), true);
    $fileName = basename($body['fileName'] ?? '');

    if (!$fileName) {
        errorResponse('fileName is required');
    }

    $path = UPLOAD_DIR . $fileName;
    if (!file_exists($path)) {
        errorResponse('File not found', 404);
    }

    if (!unlink($path)) {
        errorResponse('Failed to delete file', 500);
    }

    jsonResponse(['ok' => true]);
}

errorResponse('Method not allowed', 405);
