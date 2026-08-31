<?php
require_once dirname(__DIR__) . '/auth.php';
send_security_headers();
header('Content-Type: application/json; charset=UTF-8');

if (!auth_check()) { http_response_code(401); echo json_encode(['error'=>'認証が必要です']); exit; }

$action = preg_replace('/[^a-z]/', '', $_GET['action'] ?? '');
$userId = auth_user_id();

// 一覧取得
if ($action === 'list') {
    $convId = preg_replace('/[^a-z0-9_-]/', '', $_GET['conv_id'] ?? '');
    if ($convId) {
        $st = db()->prepare("SELECT * FROM reservations WHERE user_id=? AND conv_id=? ORDER BY start_date, start_time");
        $st->execute([$userId, $convId]);
    } else {
        $st = db()->prepare("SELECT * FROM reservations WHERE user_id=? ORDER BY start_date, start_time");
        $st->execute([$userId]);
    }
    echo json_encode($st->fetchAll());
    exit;
}

// POST系
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!csrf_verify($token)) { http_response_code(403); echo json_encode(['error'=>'CSRF失敗']); exit; }

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// 登録
if ($action === 'save') {
    $allowed = ['hotels','restaurants','sightseeing','transport','other'];
    $type      = preg_replace('/[^a-z_]/', '', $body['type'] ?? 'other');
    $title     = mb_substr(trim($body['title']    ?? ''), 0, 200);
    $location  = mb_substr(trim($body['location'] ?? ''), 0, 300);
    $url       = mb_substr(trim($body['url']      ?? ''), 0, 500);
    $convId    = preg_replace('/[^a-z0-9_-]/', '', $body['conv_id'] ?? '');
    $startDate = preg_replace('/[^0-9-]/', '', $body['start_date'] ?? '');
    $startTime = preg_replace('/[^0-9:]/', '', $body['start_time'] ?? '');
    $endDate   = preg_replace('/[^0-9-]/', '', $body['end_date']   ?? '');
    $endTime   = preg_replace('/[^0-9:]/', '', $body['end_time']   ?? '');
    $memo      = mb_substr(trim($body['memo'] ?? ''), 0, 1000);

    if (!in_array($type, $allowed, true)) $type = 'other';
    if (!$title) { http_response_code(400); echo json_encode(['error'=>'名前を入力してください']); exit; }

    // URLバリデーション
    if ($url && !filter_var($url, FILTER_VALIDATE_URL)) $url = '';

    $st = db()->prepare("INSERT INTO reservations (user_id,conv_id,type,title,location,url,start_date,start_time,end_date,end_time,memo,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $st->execute([
        $userId,
        $convId ?: null,
        $type, $title, $location ?: null, $url ?: null,
        $startDate ?: null, $startTime ?: null,
        $endDate ?: null,   $endTime ?: null,
        $memo ?: null,
        time()
    ]);
    echo json_encode(['ok'=>true, 'id'=>db()->lastInsertId()]);
    exit;
}

// 更新
if ($action === 'update') {
    $id    = (int)($body['id'] ?? 0);
    $title = mb_substr(trim($body['title']    ?? ''), 0, 200);
    $location = mb_substr(trim($body['location'] ?? ''), 0, 300);
    $url   = mb_substr(trim($body['url']      ?? ''), 0, 500);
    $startDate = preg_replace('/[^0-9-]/', '', $body['start_date'] ?? '');
    $startTime = preg_replace('/[^0-9:]/', '', $body['start_time'] ?? '');
    $endDate   = preg_replace('/[^0-9-]/', '', $body['end_date']   ?? '');
    $endTime   = preg_replace('/[^0-9:]/', '', $body['end_time']   ?? '');
    $memo  = mb_substr(trim($body['memo'] ?? ''), 0, 1000);
    if ($url && !filter_var($url, FILTER_VALIDATE_URL)) $url = '';
    if (!$id || !$title) { http_response_code(400); echo json_encode(['error'=>'invalid']); exit; }
    db()->prepare("UPDATE reservations SET title=?,location=?,url=?,start_date=?,start_time=?,end_date=?,end_time=?,memo=? WHERE id=? AND user_id=?")
        ->execute([$title, $location?:null, $url?:null, $startDate?:null, $startTime?:null, $endDate?:null, $endTime?:null, $memo?:null, $id, $userId]);
    echo json_encode(['ok'=>true]);
    exit;
}

// 削除
if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if ($id > 0) db()->prepare("DELETE FROM reservations WHERE id=? AND user_id=?")->execute([$id, $userId]);
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(400); echo json_encode(['error'=>'不正なリクエスト']);
