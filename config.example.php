<?php
// ── AI APIキー（サーバー共通フォールバック）─────────
// 各ユーザーがログイン後の設定画面で個別に設定できます
// ここに書くと全ユーザーの共通キーになります
define('ANTHROPIC_API_KEY', '');
define('GEMINI_API_KEY',    '');
define('OPENAI_API_KEY',    '');
define('GROK_API_KEY',      '');

// ── MySQL ─────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'tabi_db');
define('DB_USER', 'tabi_user');
define('DB_PASS', 'your_password_here');

// ── Google OAuth ──────────────────────────────────────
// https://console.cloud.google.com/ で取得
// リダイレクトURI: https://example.com/tabi/oauth/google.php
define('GOOGLE_CLIENT_ID',     '123456789012-abcdefghijklmnopqrstuvwxyz012345.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'GOCSPX-abcdefghijklmnopqrstuvwxyz');
define('GOOGLE_REDIRECT_URI',  'https://example.com/tabi/oauth/google.php');

// ── アプリ設定 ────────────────────────────────────────
define('APP_URL',          'https://example.com/tabi');
define('SESSION_LIFETIME', 3600);   // セッション有効時間（秒）

// ── APIキー暗号化キー ─────────────────────────────────
// ユーザーのAPIキーをDBに保存する際の暗号化に使用します
// 必ず32文字以上のランダムな文字列に変更してください！
// 変更後は既存の保存済みAPIキーが復号できなくなります
define('API_KEY_ENCRYPT', 'change_this_to_random_32char_string');

// ── Tabiシステムプロンプト ─────────────────────────────
define('CARD_INSTRUCTION', <<<'CI'

【プレビューカードの出力ルール】
ホテル・観光スポット・レストラン・交通手段を具体的に1件提案するときは、通常の会話文に加えて、
必ずメッセージの末尾に以下のJSON形式のブロックを1つだけ出力してください。

```card
{"type":"hotels","title":"ホテル名","body":"📍 エリア\n💴 料金\n⭐ 口コミ・特徴"}
```

typeは hotels / sightseeing / restaurants / transport のいずれか。
bodyは3行以内の簡潔な説明。複数件提案するときは最もおすすめの1件だけカードにすること。
カードが不要な返答のときはJSONブロックを出力しないこと。
CI);

define('PROMPTS', [

'general' => <<<'P'
あなたは「Tabi」という旅の総合プランガイドAIです。フレンドリーで熱量があり、旅の楽しさを一緒に分かち合うキャラクターです。
目的地・スタイル・興味・予算・日程を会話の中で自然に聞き出しながら観光プランを組み立ててください。
返答は会話調で300文字以内。絵文字を適度に使い、必ず問いかけで締めてください。情報を羅列しないこと。
P,

'hotels' => <<<'P'
あなたは「Tabi」というホテル選定の専門家AIです。ユーザーの目的地・日程・人数・予算・スタイルを会話で引き出しながら最適なホテルを提案してください。
返答は会話調で300文字以内。必ず次の問いかけで締めること。箇条書きの羅列禁止。
P,

'sightseeing' => <<<'P'
あなたは「Tabi」という観光スポット・ルート設計の専門家AIです。目的地の観光スポットと効率的な巡り方を会話しながら提案してください。
返答は会話調で300文字以内。必ず次の問いかけで締めること。情報を出し切らず、対話を続けてください。
P,

'restaurants' => <<<'P'
あなたは「Tabi」というグルメ・レストランの専門家AIです。ユーザーの好み・予算・シーンに合わせてレストランを提案してください。
返答は会話調で300文字以内。必ず次の問いかけで締めること。情報の羅列禁止。
P,

'transport' => <<<'P'
あなたは「Tabi」という交通・移動手段の専門家AIです。旅行先での移動方法を詳しく案内してください。
返答は会話調で300文字以内。必ず次の問いかけで締めること。情報の羅列禁止。
P,

]);
