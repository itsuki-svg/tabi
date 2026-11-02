<?php
require_once __DIR__ . '/../auth.php';
send_security_headers();

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

if ($error || !$code) {
    safe_redirect('../login?auth_error=google_cancelled');
}

// CSRF state検証
if (empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
    safe_redirect('../login?auth_error=invalid_state');
}
unset($_SESSION['oauth_state']);

// codeの長さを制限（安全のため）
if (strlen($code) > 512) {
    safe_redirect('../login?auth_error=invalid_code');
}

try {
    // アクセストークン取得
    $ctx = stream_context_create(['http'=>[
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    $tokenRes = file_get_contents('https://oauth2.googleapis.com/token', false, $ctx);
    if (!$tokenRes) throw new RuntimeException('token_failed');

    $token = json_decode($tokenRes, true);
    if (empty($token['access_token'])) throw new RuntimeException('token_invalid');

    // アクセストークンの長さ制限
    $accessToken = substr($token['access_token'], 0, 512);

    // ユーザー情報取得
    $ctx2 = stream_context_create(['http'=>[
        'header'  => "Authorization: Bearer {$accessToken}\r\n",
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    $userRes = file_get_contents('https://www.googleapis.com/oauth2/v3/userinfo', false, $ctx2);
    if (!$userRes) throw new RuntimeException('userinfo_failed');

    $googleUser = json_decode($userRes, true);
    if (empty($googleUser['sub']) || empty($googleUser['email'])) {
        throw new RuntimeException('userinfo_invalid');
    }

    $result = auth_login_google($googleUser);
    if (!empty($result['error'])) throw new RuntimeException(urlencode($result['error']));

    safe_redirect('..');

} catch (Throwable $e) {
    error_log('Google OAuth error: ' . $e->getMessage());
    safe_redirect('../login?auth_error=' . urlencode($e->getMessage()));
}