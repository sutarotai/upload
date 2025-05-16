<?php
// list_files.php
header('Content-Type: application/json; charset=utf-8');

$commonPassword = isset($_GET['commonPassword']) ? trim($_GET['commonPassword']) : '';

if ($commonPassword === '') {
    echo json_encode([]);
    exit;
}

$dataFile = __DIR__ . '/data/files.json';

if (!file_exists($dataFile)) {
    echo json_encode([]);
    exit;
}

$files = json_decode(file_get_contents($dataFile), true);
if (!is_array($files)) {
    $files = [];
}

$result = [];

foreach ($files as $file) {
    // Kiểm tra mật khẩu file hoặc mật khẩu chung hoặc admin
    if (!empty($file['password']) && $commonPassword !== $file['password'] && $commonPassword !== 'admin') {
        continue;
    }

    $result[] = [
        'id' => $file['id'] ?? '',
        'name' => $file['name'] ?? '',
        'password' => $file['password'] ?? '',
        'size' => $file['size'] ?? 0,
        'uploaded_at' => $file['uploaded_at'] ?? ''
    ];
}

echo json_encode(array_values($result), JSON_UNESCAPED_UNICODE);
?>
