<?php
require_once dirname(__DIR__) . '/auth.php';
send_security_headers();
header('Content-Type: application/json; charset=UTF-8');

if (!auth_check()) {
    http_response_code(401);
    echo json_encode(['error' => '認証が必要です']);
    exit;
}

$action = preg_replace('/[^a-z]/', '', $_GET['action'] ?? '');
$userId = auth_user_id();

if ($action === 'list') {
    $st = db()->prepare("SELECT id,title,provider,updated_at,JSON_LENGTH(messages) as cnt FROM conversations WHERE user_id=? ORDER BY updated_at DESC LIMIT 100");
    $st->execute([$userId]);
    echo json_encode($st->fetchAll());
    exit;
}

if ($action === 'get') {
    $id = preg_replace('/[^a-z0-9_-]/', '', $_GET['id'] ?? '');
    $st = db()->prepare("SELECT * FROM conversations WHERE id=? AND user_id=?");
    $st->execute([$id, $userId]);
    $row = $st->fetch();
    if (!$row) { http_response_code(404); echo json_encode(['error' => 'not found']); exit; }
    $row['messages'] = json_decode($row['messages'], true) ?? [];
    echo json_encode($row);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!csrf_verify($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF失敗']);
        exit;
    }
    if ($action === 'delete') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = preg_replace('/[^a-z0-9_-]/', '', $body['id'] ?? '');
        if ($id) {
            db()->prepare("DELETE FROM conversations WHERE id=? AND user_id=?")->execute([$id, $userId]);
            db()->prepare("DELETE FROM plan_items WHERE conv_id=? AND user_id=?")->execute([$id, $userId]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => '不正なリクエスト']);