<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );

    // users テーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        email        VARCHAR(255) NOT NULL UNIQUE,
        name         VARCHAR(100) NOT NULL DEFAULT '',
        password     VARCHAR(255) DEFAULT NULL,
        google_id    VARCHAR(100) DEFAULT NULL,
        avatar_url   VARCHAR(500) DEFAULT NULL,
        api_keys_enc TEXT         DEFAULT NULL,
        created_at   INT          NOT NULL,
        updated_at   INT          NOT NULL,
        INDEX idx_email (email),
        INDEX idx_google (google_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // conversations テーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
        id         VARCHAR(32)  PRIMARY KEY,
        user_id    INT          NOT NULL,
        title      VARCHAR(100) NOT NULL,
        provider   VARCHAR(20)  NOT NULL DEFAULT 'claude',
        category   VARCHAR(20)  NOT NULL DEFAULT 'general',
        messages   MEDIUMTEXT   NOT NULL,
        created_at INT          NOT NULL,
        updated_at INT          NOT NULL,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // plan_items テーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS plan_items (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        conv_id    VARCHAR(32)  NOT NULL,
        user_id    INT          NOT NULL,
        type       VARCHAR(20)  NOT NULL,
        title      VARCHAR(200) NOT NULL,
        body       TEXT         NOT NULL,
        created_at INT          NOT NULL,
        INDEX idx_conv (conv_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // reservations テーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS reservations (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT          NOT NULL,
        conv_id     VARCHAR(32)  DEFAULT NULL,
        type        VARCHAR(20)  NOT NULL DEFAULT 'other',
        title       VARCHAR(200) NOT NULL,
        location    VARCHAR(300) DEFAULT NULL,
        url         VARCHAR(500) DEFAULT NULL,
        start_date  DATE         DEFAULT NULL,
        start_time  VARCHAR(10)  DEFAULT NULL,
        end_date    DATE         DEFAULT NULL,
        end_time    VARCHAR(10)  DEFAULT NULL,
        memo        TEXT         DEFAULT NULL,
        created_at  INT          NOT NULL,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    return $pdo;
}

// APIキー暗号化
function encrypt_api_keys(array $keys): string {
    $key  = substr(hash('sha256', API_KEY_ENCRYPT), 0, 32);
    $iv   = random_bytes(16);
    $enc  = openssl_encrypt(json_encode($keys, JSON_UNESCAPED_UNICODE), 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $enc);
}

function decrypt_api_keys(string $data): array {
    try {
        $key  = substr(hash('sha256', API_KEY_ENCRYPT), 0, 32);
        $raw  = base64_decode($data);
        $iv   = substr($raw, 0, 16);
        $enc  = substr($raw, 16);
        $dec  = openssl_decrypt($enc, 'AES-256-CBC', $key, 0, $iv);
        return json_decode($dec, true) ?: [];
    } catch (Throwable $e) { return []; }
}