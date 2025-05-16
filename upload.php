<?php
// upload.php - upload file lên Google Drive không dùng composer

define('FOLDER_ID', '1l01tNgJzzCBIDGUaH2CONuNPJDOUVCZG');
define('SERVICE_ACCOUNT_FILE', __DIR__ . '/service-account.json');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false, 'error'=>'Phương thức không hợp lệ']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success'=>false, 'error'=>'Không tìm thấy file upload']);
    exit;
}

$file = $_FILES['file'];
$password = $_POST['password'] ?? '';

// Kiểm tra lỗi upload file
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false, 'error'=>'Lỗi khi upload file']);
    exit;
}

// 1. Tạo JWT và lấy access token
$token = getAccessToken();
if (!$token) {
    echo json_encode(['success'=>false, 'error'=>'Lấy token thất bại']);
    exit;
}

// 2. Upload file lên Google Drive
$result = uploadFileToDrive($file, $password, $token);

echo json_encode($result);

function getAccessToken() {
    $json = json_decode(file_get_contents(SERVICE_ACCOUNT_FILE), true);

    $header = base64UrlEncode(json_encode([
        'alg' => 'RS256',
        'typ' => 'JWT'
    ]));

    $now = time();
    $claim = base64UrlEncode(json_encode([
        'iss' => $json['client_email'],
        'scope' => 'https://www.googleapis.com/auth/drive',
        'aud' => $json['token_uri'],
        'exp' => $now + 3600,
        'iat' => $now,
    ]));

    $unsignedToken = $header . '.' . $claim;

    // Ký token bằng private key (RSA SHA256)
    $signature = '';
    $privateKey = openssl_pkey_get_private($json['private_key']);
    openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    $jwt = $unsignedToken . '.' . base64UrlEncode($signature);

    // Gửi request lấy access token
    $postFields = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]);

    $ch = curl_init($json['token_uri']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? false;
}

function uploadFileToDrive($file, $password, $token) {
    // Metadata
    $metadata = json_encode([
        'name' => $file['name'],
        'parents' => [FOLDER_ID]
    ]);

    // Chuẩn bị multipart request body
    $boundary = '-------314159265358979323846';
    $delimiter = "\r\n--" . $boundary . "\r\n";
    $close_delim = "\r\n--" . $boundary . "--";

    $fileData = file_get_contents($file['tmp_name']);

    $body = $delimiter .
        "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
        $metadata . $delimiter .
        "Content-Type: " . $file['type'] . "\r\n\r\n" .
        $fileData . $close_delim;

    $headers = [
        "Authorization: Bearer $token",
        "Content-Type: multipart/related; boundary=$boundary",
        "Content-Length: " . strlen($body)
    ];

    $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return ['success'=>false, 'error'=>'Lỗi CURL: ' . curl_error($ch)];
    }
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['id'])) {
        // Lưu mật khẩu file vào data/files.json
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }

        $jsonFile = $dataDir . '/files.json';
        $filesData = [];
        if (file_exists($jsonFile)) {
            $filesData = json_decode(file_get_contents($jsonFile), true);
            if (!is_array($filesData)) $filesData = [];
        }

        $filesData[] = [
            'id' => $result['id'],
            'name' => $file['name'],
            'password' => $password
        ];

        file_put_contents($jsonFile, json_encode($filesData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ['success' => true, 'fileId' => $result['id']];
    } else {
        return ['success'=>false, 'error'=> $result['error']['message'] ?? 'Upload thất bại'];
    }
}

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
