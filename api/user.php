<?php
require_once dirname(__DIR__) . '/auth.php';
send_security_headers();
header('Content-Type: application/json; charset=UTF-8');

if (!auth_check()) { http_response_code(401); echo json_encode(['error'=>'認証が必要です']); exit; }

$action = preg_replace('/[^a-z_]/', '', $_GET['action'] ?? '');
$userId = auth_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!csrf_verify($token)) { http_response_code(403); echo json_encode(['error'=>'CSRF失敗']); exit; }
}

if ($action === 'get_keys') {
    $st = db()->prepare("SELECT api_keys_enc FROM users WHERE id=?");
    $st->execute([$userId]);
    $row  = $st->fetch();
    $keys = (!empty($row['api_keys_enc'])) ? decrypt_api_keys($row['api_keys_enc']) : [];
    $masked = [];
    foreach (['claude','gemini','gpt','grok'] as $k) {
        $masked[$k] = !empty($keys[$k]) ? '***saved***' : '';
    }
    echo json_encode(['ok'=>true, 'keys'=>$masked]);
    exit;
}

if ($action === 'save_keys' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $st = db()->prepare("SELECT api_keys_enc FROM users WHERE id=?");
    $st->execute([$userId]);
    $row      = $st->fetch();
    $existing = (!empty($row['api_keys_enc'])) ? decrypt_api_keys($row['api_keys_enc']) : [];
    $newKeys  = [];
    foreach (['claude','gemini','gpt','grok'] as $k) {
        $v = mb_substr(trim($body['keys'][$k] ?? ''), 0, 300);
        $newKeys[$k] = ($v && $v !== '***saved***') ? $v : ($existing[$k] ?? '');
    }
    $enc = encrypt_api_keys($newKeys);
    db()->prepare("UPDATE users SET api_keys_enc=?,updated_at=? WHERE id=?")->execute([$enc, time(), $userId]);
    echo json_encode(['ok'=>true]);
    exit;
}

if ($action === 'update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = mb_substr(trim($body['name'] ?? ''), 0, 100);
    if (!$name) { http_response_code(400); echo json_encode(['error'=>'名前を入力してください']); exit; }
    db()->prepare("UPDATE users SET name=?,updated_at=? WHERE id=?")->execute([$name, time(), $userId]);
    $_SESSION['user']['name'] = $name;
    echo json_encode(['ok'=>true]);
    exit;
}

if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $current = $body['current'] ?? '';
    $newpw   = $body['new']     ?? '';
    $pwErr   = validate_password($newpw);
    if ($pwErr) { http_response_code(400); echo json_encode(['error'=>$pwErr]); exit; }
    $st = db()->prepare("SELECT password FROM users WHERE id=?");
    $st->execute([$userId]);
    $row = $st->fetch();
    if ($row['password'] && !password_verify($current, $row['password'])) {
        http_response_code(400); echo json_encode(['error'=>'現在のパスワードが違います']); exit;
    }
    $hash = password_hash($newpw, PASSWORD_BCRYPT, ['cost'=>12]);
    db()->prepare("UPDATE users SET password=?,updated_at=? WHERE id=?")->execute([$hash, time(), $userId]);
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(400); echo json_encode(['error'=>'不正なリクエスト']);