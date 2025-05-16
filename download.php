<?php
// download.php

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo "Thiếu tham số id";
    exit;
}

$fileId = $_GET['id'];

$dataFile = __DIR__ . '/data/files.json';
if (!file_exists($dataFile)) {
    http_response_code(404);
    echo "Không có dữ liệu file";
    exit;
}

$files = json_decode(file_get_contents($dataFile), true);
if (!$files) $files = [];

$fileData = null;
foreach ($files as $file) {
    if ($file['id'] === $fileId) {
        $fileData = $file;
        break;
    }
}

if (!$fileData) {
    http_response_code(404);
    echo "Không tìm thấy file";
    exit;
}

// Khởi tạo Google Client
require_once __DIR__ . '/vendor/autoload.php';

$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/service-account.json');
$client->addScope(Google_Service_Drive::DRIVE_READONLY);

$service = new Google_Service_Drive($client);

// Lấy file metadata
try {
    $fileMeta = $service->files->get($fileId, ['fields' => 'name, mimeType, size']);
} catch (Exception $e) {
    http_response_code(500);
    echo "Lỗi khi lấy file từ Google Drive: " . $e->getMessage();
    exit;
}

// Tải file về
try {
    $response = $service->files->get($fileId, ['alt' => 'media']);
    $content = $response->getBody()->getContents();
} catch (Exception $e) {
    http_response_code(500);
    echo "Lỗi khi tải file từ Google Drive: " . $e->getMessage();
    exit;
}

// Gửi file về client để tải
header('Content-Description: File Transfer');
header('Content-Type: ' . $fileMeta->getMimeType());
header('Content-Disposition: attachment; filename="' . basename($fileMeta->getName()) . '"');
header('Content-Length: ' . $fileMeta->getSize());
header('Cache-Control:
