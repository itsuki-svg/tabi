<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

start_secure_session();

function auth_check(): bool {
    if (empty($_SESSION['user_id'])) return false;
    if (time() > ($_SESSION['expires'] ?? 0)) {
        session_unset(); session_destroy(); return false;
    }
    $_SESSION['expires'] = time() + SESSION_LIFETIME;
    return true;
}

function auth_user(): ?array {
    return auth_check() ? ($_SESSION['user'] ?? null) : null;
}

function auth_user_id(): ?int {
    return auth_check() ? ($_SESSION['user_id'] ?? null) : null;
}

function auth_start_session(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user']    = [
        'id'         => (int)$user['id'],
        'name'       => $user['name'],
        'email'      => $user['email'],
        'avatar_url' => $user['avatar_url'] ?? '',
        'has_google' => !empty($user['google_id']),
    ];
    $_SESSION['expires'] = time() + SESSION_LIFETIME;
    $_SESSION['_regen']  = time();
}

function auth_register(string $email, string $password, string $name): array {
    $email = sanitize_email($email);
    $name  = sanitize_string($name, 100) ?: explode('@', $email)[0];

    if (!validate_email($email))   return ['error' => 'メールアドレスが無効です'];
    $pwErr = validate_password($password);
    if ($pwErr)                    return ['error' => $pwErr];

    try {
        $st = db()->prepare("SELECT id FROM users WHERE email = ?");
        $st->execute([$email]);
        if ($st->fetch()) return ['error' => 'このメールアドレスは既に登録されています'];

        $now  = time();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        db()->prepare("INSERT INTO users (email,name,password,created_at,updated_at) VALUES (?,?,?,?,?)")
           ->execute([$email, $name, $hash, $now, $now]);

        $st2 = db()->prepare("SELECT * FROM users WHERE email = ?");
        $st2->execute([$email]);
        $user = $st2->fetch();
        auth_start_session($user);
        return ['ok' => true, 'user' => $user];
    } catch (Throwable $e) {
        error_log('auth_register error: ' . $e->getMessage());
        return ['error' => '登録に失敗しました: ' . $e->getMessage()];
    }
}

function auth_login_email(string $email, string $password): array {
    $email = sanitize_email($email);
    if (!validate_email($email)) return ['error' => 'メールアドレスまたはパスワードが違います'];

    try {
        $st = db()->prepare("SELECT * FROM users WHERE email = ?");
        $st->execute([$email]);
        $user = $st->fetch();

        // タイミング攻撃対策: ユーザーが存在しない場合もverifyを実行
        $dummy = '$2y$12$invalidhashfortimingatk000000000000000000000000000';
        $hash  = $user ? ($user['password'] ?? $dummy) : $dummy;

        if (!$user || !$user['password'] || !password_verify($password, $hash)) {
            return ['error' => 'メールアドレスまたはパスワードが違います'];
        }

        // bcryptコストが低い場合は再ハッシュ
        if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            db()->prepare("UPDATE users SET password=? WHERE id=?")->execute([$newHash, $user['id']]);
        }

        auth_start_session($user);
        db()->prepare("UPDATE users SET updated_at=? WHERE id=?")->execute([time(), $user['id']]);
        return ['ok' => true];
    } catch (Throwable $e) {
        error_log('auth_login error: ' . $e->getMessage());
        return ['error' => 'ログインに失敗しました'];
    }
}

function auth_login_google(array $googleUser): array {
    $email     = sanitize_email($googleUser['email'] ?? '');
    $googleId  = preg_replace('/[^0-9]/', '', $googleUser['sub'] ?? '');
    $name      = sanitize_string($googleUser['name'] ?? '', 100);
    $avatarUrl = filter_var($googleUser['picture'] ?? '', FILTER_VALIDATE_URL) ?: '';

    if (!validate_email($email) || !$googleId) {
        return ['error' => 'Googleアカウント情報の取得に失敗しました'];
    }

    try {
        $st = db()->prepare("SELECT * FROM users WHERE email = ? OR google_id = ?");
        $st->execute([$email, $googleId]);
        $user = $st->fetch();
        $now  = time();

        if ($user) {
            db()->prepare("UPDATE users SET google_id=?,avatar_url=?,updated_at=? WHERE id=?")
               ->execute([$googleId, $avatarUrl, $now, $user['id']]);
            $user['google_id']  = $googleId;
            $user['avatar_url'] = $avatarUrl;
        } else {
            db()->prepare("INSERT INTO users (email,name,google_id,avatar_url,created_at,updated_at) VALUES (?,?,?,?,?,?)")
               ->execute([$email, $name, $googleId, $avatarUrl, $now, $now]);
            $st2 = db()->prepare("SELECT * FROM users WHERE email = ?");
            $st2->execute([$email]);
            $user = $st2->fetch();
        }

        auth_start_session($user);
        return ['ok' => true];
    } catch (Throwable $e) {
        error_log('auth_google error: ' . $e->getMessage());
        return ['error' => 'Googleログインに失敗しました'];
    }
}

function auth_logout(): void {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
    session_unset();
    session_destroy();
}