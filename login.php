<?php
require_once __DIR__ . '/auth.php';

// ログイン済みならindex.phpへ
if (auth_check()) {
    safe_redirect('.');
}

// Google OAuth開始
if (isset($_GET['google_login'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
    ]);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

send_security_headers();

$mode  = $_GET['mode'] ?? 'login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        unset($_SESSION['csrf_token']);
        csrf_token();
        $mode = ($action === 'register') ? 'register' : 'login';
    } elseif ($action === 'login') {
        if (!rate_limit('login_'.get_client_ip(), 10, 600)) {
            $error = 'ログイン試行が多すぎます。10分後に再試行してください';
        } else {
            $res = auth_login_email($_POST['email'] ?? '', $_POST['password'] ?? '');
            if (!empty($res['error'])) $error = $res['error'];
            else safe_redirect('.');
        }
    } elseif ($action === 'register') {
        if (!rate_limit('register_'.get_client_ip(), 5, 3600)) {
            $error = '登録試行が多すぎます。しばらく後に再試行してください';
            $mode  = 'register';
        } else {
            $res = auth_register($_POST['email'] ?? '', $_POST['password'] ?? '', $_POST['name'] ?? '');
            if (!empty($res['error'])) { $error = $res['error']; $mode = 'register'; }
            else safe_redirect('.');
        }
    }
}

$authError = $_GET['auth_error'] ?? '';
if ($authError) $error = match($authError) {
    'google_cancelled' => 'Googleログインがキャンセルされました',
    'invalid_state'    => 'セキュリティエラーが発生しました。再度お試しください',
    default            => 'ログインに失敗しました',
};
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tabi — ログイン</title>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f5f4f0;--bg2:#fff;--bg3:#f0ede8;--bdr:rgba(0,0,0,.1);--bdr2:rgba(0,0,0,.16);--t1:#1a1a18;--t2:#6b6a65;--t3:#9e9d99;--a1:#5b63f0;--a2:#7c5cbf}
@media(prefers-color-scheme:dark){:root{--bg:#111110;--bg2:#1c1b19;--bg3:#242320;--bdr:rgba(255,255,255,.1);--bdr2:rgba(255,255,255,.18);--t1:#f0ede8;--t2:#9e9d99;--t3:#6b6a65}}
body{font-family:'Noto Sans JP',system-ui,sans-serif;background:var(--bg);color:var(--t1);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{width:100%;max-width:380px;background:var(--bg2);border:0.5px solid var(--bdr2);border-radius:18px;padding:36px 30px;box-shadow:0 4px 32px rgba(0,0,0,.08)}
.logo{display:flex;align-items:center;gap:10px;justify-content:center;margin-bottom:24px}
.logo-mark{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#5b63f0,#7c5cbf);display:flex;align-items:center;justify-content:center;font-size:20px}
.logo-name{font-size:22px;font-weight:600}
.tabs{display:flex;background:var(--bg3);border-radius:10px;padding:3px;margin-bottom:24px}
.tab{flex:1;padding:8px;border:none;border-radius:8px;background:transparent;color:var(--t2);font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;transition:all .15s}
.tab.on{background:var(--bg2);color:var(--t1);box-shadow:0 1px 4px rgba(0,0,0,.08)}
.field{margin-bottom:14px}
.label{display:block;font-size:12px;font-weight:500;color:var(--t2);margin-bottom:5px}
.input{width:100%;padding:11px 13px;background:var(--bg3);border:0.5px solid var(--bdr2);border-radius:10px;color:var(--t1);font-size:14px;font-family:inherit;outline:none;transition:border-color .2s}
.input:focus{border-color:var(--a1)}
.btn{width:100%;padding:12px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--a1),var(--a2));color:#fff;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit;margin-bottom:12px}
.btn:hover{opacity:.9}
.divider{display:flex;align-items:center;gap:10px;margin:16px 0;color:var(--t3);font-size:12px}
.divider::before,.divider::after{content:'';flex:1;height:0.5px;background:var(--bdr2)}
.google-btn{width:100%;padding:11px;border:0.5px solid var(--bdr2);border-radius:10px;background:var(--bg2);color:var(--t1);font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;transition:all .15s}
.google-btn:hover{border-color:var(--a1);background:var(--bg3)}
.google-icon{width:18px;height:18px;flex-shrink:0}
.err{padding:9px 12px;background:rgba(220,38,38,.08);border:0.5px solid rgba(220,38,38,.3);border-radius:8px;color:#dc2626;font-size:12px;margin-bottom:14px;line-height:1.5}
.foot{text-align:center;font-size:11px;color:var(--t3);margin-top:18px}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-mark">✈️</div>
    <div class="logo-name">Tabi</div>
  </div>

  <div class="tabs">
    <button class="tab <?= $mode==='login'?'on':'' ?>" onclick="switchMode('login')">ログイン</button>
    <button class="tab <?= $mode==='register'?'on':'' ?>" onclick="switchMode('register')">新規登録</button>
  </div>

  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- ログイン -->
  <form id="form-login" method="post" action="login.php" style="display:<?= $mode==='login'?'block':'none' ?>">
    <input type="hidden" name="action" value="login">
    <?= csrf_input() ?>
    <div class="field">
      <label class="label">メールアドレス</label>
      <input class="input" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
    </div>
    <div class="field">
      <label class="label">パスワード</label>
      <input class="input" type="password" name="password" placeholder="パスワード" autocomplete="current-password" required>
    </div>
    <button class="btn" type="submit">ログイン</button>
  </form>

  <!-- 新規登録 -->
  <form id="form-register" method="post" action="login.php" style="display:<?= $mode==='register'?'block':'none' ?>">
    <input type="hidden" name="action" value="register">
    <?= csrf_input() ?>
    <div class="field">
      <label class="label">名前</label>
      <input class="input" type="text" name="name" placeholder="山田 太郎" autocomplete="name">
    </div>
    <div class="field">
      <label class="label">メールアドレス</label>
      <input class="input" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
    </div>
    <div class="field">
      <label class="label">パスワード（英数字8文字以上）</label>
      <input class="input" type="password" name="password" placeholder="パスワード" autocomplete="new-password" required minlength="8">
    </div>
    <button class="btn" type="submit">アカウントを作成</button>
  </form>

  <div class="divider">または</div>

  <a href="login.php?google_login=1" class="google-btn">
    <svg class="google-icon" viewBox="0 0 24 24">
      <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
      <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
      <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
      <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
    </svg>
    Googleでログイン / 登録
  </a>

  <div class="foot">セッション <?= SESSION_LIFETIME/60 ?>分 ／ 失敗 <?= MAX_ATTEMPTS ?>回でロック</div>
</div>

<script>
function switchMode(mode){
  document.querySelectorAll('.tab').forEach(function(t,i){t.classList.toggle('on',i===(mode==='login'?0:1));});
  document.getElementById('form-login').style.display  = mode==='login'    ? 'block' : 'none';
  document.getElementById('form-register').style.display = mode==='register' ? 'block' : 'none';
}
</script>
</body>
</html>