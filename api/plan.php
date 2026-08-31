<?php
require_once dirname(__DIR__) . '/auth.php';
send_security_headers();
header('Content-Type: application/json; charset=UTF-8');

if (!auth_check()) { http_response_code(401); echo json_encode(['error'=>'認証が必要です']); exit; }

$action = preg_replace('/[^a-z]/', '', $_GET['action'] ?? '');
$userId = auth_user_id();

if ($action === 'list') {
    $convId = preg_replace('/[^a-z0-9_-]/', '', $_GET['conv_id'] ?? '');
    if (!$convId) { echo json_encode([]); exit; }
    $st = db()->prepare("SELECT * FROM plan_items WHERE conv_id=? AND user_id=? ORDER BY type,id");
    $st->execute([$convId, $userId]);
    echo json_encode($st->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit;
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!csrf_verify($token)) { http_response_code(403); echo json_encode(['error'=>'CSRF失敗']); exit; }

$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'save') {
    $convId = preg_replace('/[^a-z0-9_-]/', '', $body['conv_id'] ?? '');
    $type   = preg_replace('/[^a-z_]/', '', $body['type'] ?? '');
    $title  = mb_substr(trim($body['title'] ?? ''), 0, 200);
    $pbody  = mb_substr(trim($body['body']  ?? ''), 0, 2000);
    $allowed = ['hotels','sightseeing','restaurants','transport'];
    if (!$convId || !in_array($type, $allowed, true) || !$title) {
        http_response_code(400); echo json_encode(['error'=>'無効なリクエスト']); exit;
    }
    $st = db()->prepare("SELECT id FROM conversations WHERE id=? AND user_id=?");
    $st->execute([$convId, $userId]);
    if (!$st->fetch()) { http_response_code(404); echo json_encode(['error'=>'会話が見つかりません']); exit; }
    $st2 = db()->prepare("INSERT INTO plan_items (conv_id,user_id,type,title,body,created_at) VALUES (?,?,?,?,?,?)");
    $st2->execute([$convId, $userId, $type, $title, $pbody, time()]);
    echo json_encode(['ok'=>true, 'id'=>db()->lastInsertId()]);
    exit;
}

if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if ($id > 0) db()->prepare("DELETE FROM plan_items WHERE id=? AND user_id=?")->execute([$id, $userId]);
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(400); echo json_encode(['error'=>'不正なリクエスト']);