<?php
/**
 * Tabi — セキュリティヘルパー
 * 全ファイルで require_once する
 */

// ── エラー表示を本番環境では完全に非表示 ──────────────
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ── セキュリティヘッダー ───────────────────────────────
function send_security_headers(): void {
    // クリックジャッキング防止
    header('X-Frame-Options: DENY');
    // MIMEスニッフィング防止
    header('X-Content-Type-Options: nosniff');
    // XSS保護（モダンブラウザ用）
    header('X-XSS-Protection: 1; mode=block');
    // リファラー制御
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com; " .
        "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; " .
        "img-src 'self' data: https://lh3.googleusercontent.com; " .
        "connect-src 'self' https://cdn.jsdelivr.net; " .
        "frame-ancestors 'none';");
    // HTTPS強制（さくらの場合は実際にHTTPSで運用していること前提）
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    // キャッシュ制御（認証ページ）
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

// ── CSRF トークン ──────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(string $token): bool {
    // セッションにトークンがない（新規セッション）場合は再生成して失敗扱い
    if (empty($_SESSION['csrf_token'])) return false;
    if (empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

// ── APIリクエストのCSRF検証 ───────────────────────────
function csrf_verify_api(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!csrf_verify($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF検証失敗']);
        exit;
    }
}

// ── レートリミット ─────────────────────────────────────
function rate_limit(string $key, int $maxAttempts, int $windowSec): bool {
    $sessionKey = 'rl_' . $key;
    $now        = time();
    $data       = $_SESSION[$sessionKey] ?? ['count' => 0, 'reset' => $now + $windowSec];

    // ウィンドウリセット
    if ($now > $data['reset']) {
        $data = ['count' => 0, 'reset' => $now + $windowSec];
    }

    $data['count']++;
    $_SESSION[$sessionKey] = $data;

    return $data['count'] <= $maxAttempts;
}

function rate_limit_remaining(string $key): int {
    $data = $_SESSION['rl_' . $key] ?? null;
    if (!$data) return 0;
    return max(0, $data['reset'] - time());
}

// ── 入力サニタイズ ─────────────────────────────────────
function sanitize_string(string $val, int $maxLen = 255): string {
    return mb_substr(trim($val), 0, $maxLen);
}

function sanitize_email(string $val): string {
    return strtolower(trim(filter_var($val, FILTER_SANITIZE_EMAIL)));
}

function validate_email(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ── IPアドレス取得（プロキシ考慮） ────────────────────
function get_client_ip(): string {
    // さくらではX-Forwarded-Forは基本不要だが念のため制限
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ── 安全なJSONレスポンス ───────────────────────────────
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── リダイレクト（オープンリダイレクト防止） ──────────
function safe_redirect(string $path): void {
    // 相対パスのみ許可
    if (preg_match('/^https?:\/\//i', $path)) {
        $path = 'index.php';
    }
    header('Location: ' . $path);
    exit;
}

// ── パスワード強度チェック ─────────────────────────────
function validate_password(string $pw): ?string {
    if (mb_strlen($pw) < 8)  return 'パスワードは8文字以上にしてください';
    if (mb_strlen($pw) > 128) return 'パスワードが長すぎます';
    if (!preg_match('/[A-Za-z]/', $pw)) return 'パスワードにはアルファベットを含めてください';
    if (!preg_match('/[0-9]/', $pw))    return 'パスワードには数字を含めてください';
    return null;
}

// ── セッション設定（呼び出し前に必ず実行） ───────────
function start_secure_session(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    // 絶対パスでsessions/を指定（どのファイルから呼ばれても同じ場所）
    // security.phpはtabi/直下にあるので__DIR__がtabi/になる
    $sessDir = __DIR__ . '/sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0755, true);
    }
    session_save_path($sessDir);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // さくらサーバーはENC_プレフィックスでセッションCookieを暗号化するため
    // デフォルトのPHPSESSIDを使用する
    session_name('PHPSESSID');
    session_start();

    // 定期的なセッションID再生成（30分ごと・ログイン済みのみ）
    if (!empty($_SESSION['user_id'])) {
        if (empty($_SESSION['_regen'])) {
            $_SESSION['_regen'] = time();
        } elseif (time() - $_SESSION['_regen'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_regen'] = time();
        }
    }
}