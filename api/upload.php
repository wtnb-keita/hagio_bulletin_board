<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(null, 204);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

if (!isset($_FILES['file'])) {
    errorResponse('No file uploaded');
}

$file     = $_FILES['file'];
$allowed  = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
$maxBytes = 20 * 1024 * 1024; // 20MB

if (!in_array($file['type'], $allowed)) {
    errorResponse('Unsupported file type: ' . $file['type']);
}

if ($file['size'] > $maxBytes) {
    errorResponse('File too large (max 20MB)');
}

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

$ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
$newName  = uniqid('', true) . '.' . strtolower($ext);
$destPath = UPLOAD_DIR . $newName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    errorResponse('Failed to save file', 500);
}

jsonResponse([
    'ok'       => true,
    'fileName' => $file['name'],
    'fileType' => $file['type'],
    'filePath' => uploadUrlBase() . $newName,
]);
