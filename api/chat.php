<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/config.php';

// SSEはContent-Type固有なので先にチェック
if (!auth_check()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => '認証が必要です']);
    exit;
}

// CSRF検証（SSEなのでヘッダーで受け取る）
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!csrf_verify($csrfToken)) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'CSRF検証失敗']);
    exit;
}

// レートリミット: 1時間に100回まで
$userId = auth_user_id();
if (!rate_limit('chat_'.$userId, 100, 3600)) {
    header('Content-Type: application/json');
    http_response_code(429);
    echo json_encode(['error' => 'リクエストが多すぎます。しばらく後に再試行してください']);
    exit;
}

header('Content-Type: text/event-stream; charset=UTF-8');
header('Cache-Control: no-store, no-cache');
header('X-Accel-Buffering: no');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_error('POST のみ'); exit; }

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$messages  = array_slice($body['messages']  ?? [], -20);
$provider  = preg_replace('/[^a-z]/', '', $body['provider'] ?? 'claude');
$convId    = preg_replace('/[^a-z0-9_-]/', '', $body['convId'] ?? '');
$convTitle = sanitize_string($body['convTitle'] ?? '', 100);
$category  = preg_replace('/[^a-z]/', '', $body['category'] ?? 'general');

// メッセージのサニタイズ（roleとcontentのみ許可）
$allowed_roles = ['user', 'assistant'];
$messages = array_filter(array_map(function($m) use ($allowed_roles) {
    if (!is_array($m)) return null;
    $role    = in_array($m['role'] ?? '', $allowed_roles, true) ? $m['role'] : null;
    $content = sanitize_string($m['content'] ?? '', 10000);
    return ($role && $content) ? ['role' => $role, 'content' => $content] : null;
}, $messages));
$messages = array_values(array_filter($messages));

// プロバイダー許可リスト
if (!in_array($provider, ['claude','gemini','gpt','grok'], true)) {
    send_error('不明なプロバイダー'); exit;
}

// カテゴリ許可リスト
$allowed_cats = ['general','hotels','sightseeing','restaurants','transport'];
if (!in_array($category, $allowed_cats, true)) $category = 'general';

// APIキー: DBから取得（フロントからは受け取らない）
$dbKeys = [];
try {
    $st = db()->prepare("SELECT api_keys_enc FROM users WHERE id=?");
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!empty($row['api_keys_enc'])) $dbKeys = decrypt_api_keys($row['api_keys_enc']);
} catch (Throwable $e) { error_log('key load error: '.$e->getMessage()); }

// 各プロバイダーのキー（DB優先、なければconfig.php）
$keys = [
    'claude' => !empty($dbKeys['claude']) ? $dbKeys['claude'] : ANTHROPIC_API_KEY,
    'gemini' => !empty($dbKeys['gemini']) ? $dbKeys['gemini'] : GEMINI_API_KEY,
    'gpt'    => !empty($dbKeys['gpt'])    ? $dbKeys['gpt']    : OPENAI_API_KEY,
    'grok'   => !empty($dbKeys['grok'])   ? $dbKeys['grok']   : GROK_API_KEY,
];

// 登録済みキーの一覧（優先順位順）
$available = [];
foreach (['claude','gemini','gpt','grok'] as $p) {
    if (!empty($keys[$p])) $available[] = $p;
}

// 選択プロバイダーにキーがない場合、登録済みのキーで代用
if (!empty($keys[$provider])) {
    $actualProvider = $provider;
} elseif (!empty($available)) {
    $actualProvider = $available[0];
    send_chunk_info("⚠️ {$provider}のAPIキーが未登録のため、{$actualProvider}で代用します。");
} else {
    send_error('APIキーが登録されていません。設定画面でAPIキーを登録してください。');
    exit;
}

// 実際に使用するキーとプロバイダー
$useKey      = $keys[$actualProvider];
$useProvider = $actualProvider;

$prompts   = PROMPTS;
$sysprompt = ($prompts[$category] ?? $prompts['general']) . CARD_INSTRUCTION;

$GLOBALS['_acc'] = '';
$GLOBALS['_sys'] = $sysprompt;

try {
    match ($useProvider) {
        'claude' => stream_claude($useKey, $messages),
        'gemini' => stream_gemini($useKey, $messages),
        'gpt'    => stream_gpt($useKey,    $messages),
        'grok'   => stream_grok($useKey,   $messages),
    };

    if ($convId && $GLOBALS['_acc'] && $userId) {
        if (!$convTitle) {
            foreach ($messages as $m) {
                if ($m['role'] === 'user') { $convTitle = mb_substr($m['content'], 0, 50); break; }
            }
            $convTitle = $convTitle ?: '新しい会話';
        }
        $allMsgs   = $messages;
        $allMsgs[] = ['role'=>'assistant','content'=>$GLOBALS['_acc']];
        $json      = json_encode($allMsgs, JSON_UNESCAPED_UNICODE);
        $now       = time();
        try {
            db()->prepare("INSERT INTO conversations (id,user_id,title,provider,category,messages,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE title=VALUES(title),provider=VALUES(provider),category=VALUES(category),messages=VALUES(messages),updated_at=VALUES(updated_at)")
              ->execute([$convId, $userId, $convTitle, $provider, $category, $json, $now, $now]);
        } catch (Throwable $e) { error_log('conv save error: '.$e->getMessage()); }
    }
} catch (Throwable $e) {
    error_log('chat error: '.$e->getMessage());
    send_error('AIとの通信中にエラーが発生しました');
}

// ── SSE/cURL helpers ────────────────────────────────
function send_chunk(string $text): void {
    $GLOBALS['_acc'] .= $text;
    echo 'data: ' . json_encode(['text'=>$text], JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}
function send_chunk_info(string $text): void {
    // 代用通知（会話履歴には含めない）
    echo 'data: ' . json_encode(['text'=>$text."\n\n"], JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}
function send_error(string $msg): void {
    echo 'data: ' . json_encode(['error'=>$msg], JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}
function send_done(): void { echo "data: [DONE]\n\n"; flush(); }

function curl_stream(string $url, array $headers, array $payload, callable $handler): void {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_POSTFIELDS      => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER      => $headers,
        CURLOPT_RETURNTRANSFER  => false,
        CURLOPT_TIMEOUT         => 120,
        CURLOPT_SSL_VERIFYPEER  => true,
        CURLOPT_SSL_VERIFYHOST  => 2,
        CURLOPT_WRITEFUNCTION   => function($ch, $data) use ($handler) {
            static $buf = '';
            $buf .= $data;
            while (($pos = strpos($buf, "\n")) !== false) {
                $handler(rtrim(substr($buf, 0, $pos)));
                $buf = substr($buf, $pos + 1);
            }
            return strlen($data);
        },
    ]);
    curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) throw new RuntimeException('通信エラーが発生しました');
}

function stream_claude(string $key, array $msgs): void {
    if (!$key) throw new RuntimeException('Claude APIキーが設定されていません');
    curl_stream('https://api.anthropic.com/v1/messages',
        ['Content-Type: application/json','x-api-key: '.$key,'anthropic-version: 2023-06-01'],
        ['model'=>'claude-sonnet-4-20250514','max_tokens'=>1024,'system'=>$GLOBALS['_sys'],'stream'=>true,'messages'=>$msgs],
        function($line){
            if (!str_starts_with($line,'data: ')) return;
            $e = json_decode(substr($line,6), true);
            if (($e['type']??'')==='content_block_delta') send_chunk($e['delta']['text']??'');
        }
    ); send_done();
}
function stream_gemini(string $key, array $msgs): void {
    if (!$key) throw new RuntimeException('Gemini APIキーが設定されていません');
    $contents = array_map(fn($m)=>['role'=>$m['role']==='assistant'?'model':'user','parts'=>[['text'=>$m['content']]]],$msgs);
    curl_stream("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:streamGenerateContent?key={$key}&alt=sse",
        ['Content-Type: application/json'],
        ['system_instruction'=>['parts'=>[['text'=>$GLOBALS['_sys']]]],'contents'=>$contents,'generationConfig'=>['maxOutputTokens'=>1024,'temperature'=>0.8]],
        function($line){
            if (!str_starts_with($line,'data: ')) return;
            $e = json_decode(substr($line,6), true);
            $t = $e['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($t) send_chunk($t);
        }
    ); send_done();
}
function stream_gpt(string $key, array $msgs): void {
    if (!$key) throw new RuntimeException('OpenAI APIキーが設定されていません');
    curl_stream('https://api.openai.com/v1/chat/completions',
        ['Content-Type: application/json','Authorization: Bearer '.$key],
        ['model'=>'gpt-4o','max_tokens'=>1024,'stream'=>true,'messages'=>array_merge([['role'=>'system','content'=>$GLOBALS['_sys']]],$msgs)],
        function($line){
            if (!str_starts_with($line,'data: ')) return;
            $j = substr($line,6); if ($j==='[DONE]') return;
            $e = json_decode($j, true);
            $t = $e['choices'][0]['delta']['content'] ?? null;
            if ($t) send_chunk($t);
        }
    ); send_done();
}
function stream_grok(string $key, array $msgs): void {
    if (!$key) throw new RuntimeException('Grok APIキーが設定されていません');
    curl_stream('https://api.x.ai/v1/chat/completions',
        ['Content-Type: application/json','Authorization: Bearer '.$key],
        ['model'=>'grok-3','max_tokens'=>1024,'stream'=>true,'messages'=>array_merge([['role'=>'system','content'=>$GLOBALS['_sys']]],$msgs)],
        function($line){
            if (!str_starts_with($line,'data: ')) return;
            $j = substr($line,6); if ($j==='[DONE]') return;
            $e = json_decode($j, true);
            $t = $e['choices'][0]['delta']['content'] ?? null;
            if ($t) send_chunk($t);
        }
    ); send_done();
}