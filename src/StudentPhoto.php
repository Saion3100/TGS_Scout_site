<?php
declare(strict_types=1);

final class StudentPhoto
{
    public static function configurationError(): string
    {
        foreach (['curl', 'openssl', 'gd'] as $extension) {
            if (!extension_loaded($extension)) return '写真機能にはPHPの' . $extension . '拡張が必要です。';
        }
        if (!is_readable((string) getenv('TGS_GOOGLE_CREDENTIALS'))) return '写真保存用のGoogle認証情報が未設定です。';
        return '';
    }

    private static function request(string $url, array $headers = [], ?string $body = null, ?string $method = null): string
    {
        if (!function_exists('curl_init')) throw new RuntimeException('cURL is required.');
        $handle = curl_init($url);
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45, CURLOPT_FOLLOWLOCATION => false]);
        if ($body !== null) curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body]);
        if ($method !== null) curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        $response = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        if (!is_string($response) || $status < 200 || $status >= 300) throw new RuntimeException('Google Drive request failed (HTTP ' . $status . ').');
        return $response;
    }

    private static function token(): string
    {
        static $token;
        if ($token) return $token;
        $path = (string) getenv('TGS_GOOGLE_CREDENTIALS');
        if (!is_readable($path) || !function_exists('openssl_sign')) throw new RuntimeException('Google credentials or OpenSSL unavailable.');
        $key = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($key['type'] ?? '') !== 'service_account' || empty($key['client_email']) || empty($key['private_key'])) throw new RuntimeException('Invalid service account credentials.');
        $encode = static function (string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); };
        $now = time();
        $jwt = $encode('{"alg":"RS256","typ":"JWT"}') . '.' . $encode(json_encode([
            'iss' => $key['client_email'], 'scope' => 'https://www.googleapis.com/auth/drive',
            'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        if (!openssl_sign($jwt, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) throw new RuntimeException('Could not sign Google token.');
        $response = json_decode(self::request('https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt . '.' . $encode($signature),
        ])), true, 512, JSON_THROW_ON_ERROR);
        if (empty($response['access_token'])) throw new RuntimeException('Google access token missing.');
        return $token = $response['access_token'];
    }

    public static function metadata(string $id): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $id)) throw new InvalidArgumentException('画像IDが不正です。');
        return json_decode(self::request('https://www.googleapis.com/drive/v3/files/' . rawurlencode($id)
            . '?supportsAllDrives=true&fields=id,mimeType,driveId,parents,appProperties,trashed,capabilities(canAddChildren,canTrash)',
            ['Authorization: Bearer ' . self::token()]), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function upload(array $file, string $studentId): string
    {
        $error = self::configurationError();
        if ($error !== '') throw new InvalidArgumentException($error);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('写真を再選択してください。アップロードに失敗しました。');
        if (filesize($file['tmp_name']) > 10 * 1024 * 1024) throw new InvalidArgumentException('写真は10MB以下にしてください。');
        $info = @getimagesize($file['tmp_name']);
        if (!$info || $info[0] < 3 || $info[1] < 4 || $info[0] > 1200 || $info[1] > 1600 || $info[0] * 4 !== $info[1] * 3 || $info[2] !== IMAGETYPE_JPEG) throw new InvalidArgumentException('写真のトリミングを確定してください（横3：縦4のJPEG）。');
        $img = @imagecreatefromjpeg($file['tmp_name']);
        if (!$img) throw new InvalidArgumentException('写真を読み込めません。');
        ob_start();
        try { if (!imagejpeg($img, null, 90)) throw new RuntimeException('JPEG encoding failed.'); $jpeg = ob_get_contents(); }
        finally { ob_end_clean(); imagedestroy($img); }
        $folder = (string) (getenv('TGS_GOOGLE_PHOTO_FOLDER_ID') ?: '1nou3qIrmoeUR1LXg4SCJciLevDG_0K3m');
        $metadata = self::metadata($folder);
        if (($metadata['mimeType'] ?? '') !== 'application/vnd.google-apps.folder' || empty($metadata['driveId']) || !empty($metadata['trashed']) || empty($metadata['capabilities']['canAddChildren'])) throw new InvalidArgumentException('保存先の共有ドライブと書き込み権限を確認してください。');
        $boundary = 'tgs_' . bin2hex(random_bytes(16));
        $body = '--' . $boundary . "\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n" . json_encode([
            'name' => 'student-' . $studentId . '-' . bin2hex(random_bytes(8)) . '.jpg', 'parents' => [$folder],
            'appProperties' => ['tgs_photo' => 'v1', 'student_id' => $studentId],
        ], JSON_THROW_ON_ERROR) . "\r\n--" . $boundary . "\r\nContent-Type: image/jpeg\r\n\r\n" . $jpeg . "\r\n--" . $boundary . "--\r\n";
        $result = json_decode(self::request('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id',
            ['Authorization: Bearer ' . self::token(), 'Content-Type: multipart/related; boundary=' . $boundary], $body), true, 512, JSON_THROW_ON_ERROR);
        if (empty($result['id'])) throw new RuntimeException('Uploaded file ID missing.');
        return $result['id'];
    }

    public static function download(string $id): string
    {
        $metadata = self::metadata($id);
        if (!empty($metadata['trashed']) || ($metadata['mimeType'] ?? '') !== 'image/jpeg') throw new RuntimeException('Photo unavailable.');
        return self::request('https://www.googleapis.com/drive/v3/files/' . rawurlencode($id) . '?alt=media&supportsAllDrives=true', ['Authorization: Bearer ' . self::token()]);
    }

    public static function isManagedPhoto(array $metadata, string $studentId, string $folder): bool
    {
        return ($metadata['appProperties']['tgs_photo'] ?? '') === 'v1'
            && ($metadata['appProperties']['student_id'] ?? '') === $studentId
            && ($metadata['mimeType'] ?? '') === 'image/jpeg'
            && !empty($metadata['driveId'])
            && in_array($folder, $metadata['parents'] ?? [], true);
    }

    // Call only after committing the new photo reference to the student repository.
    public static function trashPrevious(array $previous, array $current, array $savedStudents): void
    {
        $oldId = (string) ($previous['photo_drive_file_id'] ?? '');
        $newId = (string) ($current['photo_drive_file_id'] ?? '');
        if ($oldId === '' || $newId === '' || $oldId === $newId) return;
        // Do not remove an image still referenced by any student.
        foreach ($savedStudents as $saved) {
            if (($saved['photo_drive_file_id'] ?? '') === $oldId) return;
            foreach (['photo_url', 'icon_url'] as $field) {
                if (strpos((string) ($saved[$field] ?? ''), $oldId) !== false) return;
            }
        }
        $folder = (string) (getenv('TGS_GOOGLE_PHOTO_FOLDER_ID') ?: '1nou3qIrmoeUR1LXg4SCJciLevDG_0K3m');
        $metadata = self::metadata($oldId);
        if (!self::isManagedPhoto($metadata, (string) $current['id'], $folder) || !empty($metadata['trashed'])) return;
        if (empty($metadata['capabilities']['canTrash'])) throw new RuntimeException('Old photo cannot be trashed: insufficient Drive permissions.');
        self::request('https://www.googleapis.com/drive/v3/files/' . rawurlencode($oldId) . '?supportsAllDrives=true&fields=id,trashed',
            ['Authorization: Bearer ' . self::token(), 'Content-Type: application/json'], '{"trashed":true}', 'PATCH');
    }
}
