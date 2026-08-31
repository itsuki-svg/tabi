# Tabi — 旅の相棒 AI

複数の LLM（Claude / Gemini / GPT / Grok）を切り替えながら旅行プランを作成できる AI チャットアプリです。

## Features

- **マルチ LLM 対応** — Claude・Gemini・GPT・Grok を UI 上でワンクリック切り替え。API キーが未登録のプロバイダーは自動的に代替
- **SSE ストリーミング** — サーバー送信イベントでリアルタイムにレスポンスを表示
- **プレビューカード** — AI がホテル・観光スポット・グルメ・交通を提案すると、予約サイトへのリンク付きカードを自動生成
- **プランボード** — 気に入った提案を「プランに追加」でカテゴリ別に保存
- **予約管理** — 日程・時間・URL 付きで予約情報を登録、日付順に一覧表示
- **Google OAuth / メール認証** — Google アカウントまたはメール＋パスワードでログイン
- **ユーザー別 API キー** — 各ユーザーが自分の API キーを暗号化保存し、任意のプロバイダーを利用可能

## LLM Provider Switching

ユーザーがプロバイダーを選択すると、まずユーザー個別キー（AES-256-CBC で暗号化し DB に保存）を復号して使用します。個別キーが未登録の場合はサーバー共通キー（config.php）にフォールバックし、共通キーも空なら別プロバイダーに自動切り替えします。選択されたプロバイダーの API に curl でストリーミングリクエストを送り、SSE でブラウザにリアルタイム転送します。

## Database Schema

| テーブル | 概要 | 主なカラム |
|---------|------|-----------|
| `users` | ユーザー | id, email, name, password (bcrypt), google_id, avatar_url, api_keys_enc (AES-256) |
| `conversations` | 会話 | id (VARCHAR 32), user_id, title, provider, category, messages (MEDIUMTEXT JSON) |
| `plan_items` | プランアイテム | id, conv_id, user_id, type (hotels/sightseeing/restaurants/transport), title, body |
| `reservations` | 予約 | id, user_id, conv_id, type, title, location, url, start_date, start_time, end_date, end_time, memo |

## API Endpoints

| Method | Path | 認証 | 概要 |
|--------|------|------|------|
| `POST` | `/api/chat.php` | Session | LLM チャット（SSE ストリーミング） |
| `GET` | `/api/history.php?action=list` | Session | 会話履歴一覧 |
| `POST` | `/api/history.php?action=save` | Session | 会話保存 |
| `POST` | `/api/history.php?action=delete` | Session | 会話削除 |
| `GET` | `/api/plan.php?action=list` | Session | プランアイテム取得 |
| `POST` | `/api/plan.php?action=save` | Session | プランに追加 |
| `POST` | `/api/plan.php?action=delete` | Session | プランから削除 |
| `GET` | `/api/reservations.php?action=list` | Session | 予約一覧 |
| `POST` | `/api/reservations.php?action=save` | Session | 予約登録/更新 |
| `POST` | `/api/reservations.php?action=delete` | Session | 予約削除 |
| `POST` | `/api/user.php?action=save_keys` | Session | API キー保存（暗号化） |
| `POST` | `/api/user.php?action=change_password` | Session | パスワード変更 |

## Security

| 項目 | 実装 |
|------|------|
| API キー保管 | AES-256-CBC で暗号化後 DB に保存。復号キーは config 管理 |
| 認証 | Google OAuth 2.0 + メール/パスワード (bcrypt) の二方式 |
| CSRF | セッション紐付きトークン。全 POST リクエストで検証 |
| レートリミット | IP ベースの API 呼び出し制限（config で閾値設定可） |
| CSP ヘッダー | `Content-Security-Policy` でインラインスクリプト・外部リソースを制限 |
| セッション | httponly, secure, strict_mode, samesite=Lax |

## Tech Stack

| 項目 | 内容 |
|------|------|
| Backend | PHP 8.x |
| Database | MySQL 8.0 (utf8mb4) |
| AI | Claude API (Sonnet 4), Gemini API (2.0 Flash), OpenAI API (GPT-4o), Grok API (Grok-3) |
| Auth | Google OAuth 2.0, bcrypt パスワードハッシュ |
| Streaming | Server-Sent Events (SSE) |
| Security | CSRF トークン, CSP ヘッダー, レートリミット, AES-256 API キー暗号化 |
| Frontend | Vanilla JS, Noto Sans JP, Tabler Icons |

## Directory Structure

```
tabi/
├── index.php           # メイン画面（チャット・プランボード・予約管理）
├── login.php           # ログイン / 新規登録
├── config.php          # 設定（config.example.php を参照）
├── db.php              # DB接続・テーブル自動作成・暗号化ヘルパー
├── auth.php            # 認証ロジック（メール / Google OAuth）
├── security.php        # CSRF・レートリミット・セキュリティヘッダー
├── api/
│   ├── chat.php        # LLM ストリーミング API（SSE）
│   ├── history.php     # 会話履歴 CRUD
│   ├── plan.php        # プランアイテム CRUD
│   ├── user.php        # APIキー保存・プロフィール更新
│   └── reservations.php # 予約 CRUD
└── oauth/
    └── google.php      # Google OAuth コールバック
```

## Setup

1. `config.example.php` を `config.php` にコピーし、DB 情報・Google OAuth・暗号化キーを設定
2. 初回アクセス時にテーブルが自動作成されます
3. 各ユーザーは設定画面から自分の API キーを登録できます
