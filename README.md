# 🔮 おみくじENJINボット (LINE Messaging API ✕ Claude API ✕ PHP)

LINEで話しかけると、AI（Claude）が「親しみやすい占い師」として返信してくれるLINE Botアプリケーションです。
生年月日・性別・名前などの情報を会話の中で記憶し、星座・数秘術・九星気学などの占いを交えて前向きなアドバイスを返します。
さくらインターネット（レンタルサーバー）の環境特性を考慮し、セキュアかつ安定して稼働するバックエンドロジックをPHPで構築しました。

## 🚀 成果物URL
* **LINE公式アカウント（友だち追加QRコード/URL）**
  * <img width="360" height="360" alt="image" src="https://github.com/user-attachments/assets/042f2bfb-5b56-4cf8-92ab-4f14717e02f0" />

---

## 🛠️ 主な機能
1. **AI占い師との対話**
   * ユーザーのメッセージを Claude API（Anthropic）に渡し、占い師キャラクターとして親しみやすいトーンで返信します。
   * 星座・数秘術・九星気学・血液型などの知識を使い、短文で具体的かつ前向きなアドバイスを返します。
2. **会話履歴の記憶（コンテキスト保持）**
   * ユーザーごとに会話履歴をサーバー上のJSONファイルに保存し、直近20件（10往復分）を次のリクエストに引き継ぎます。
   * これにより、一度教えた生年月日や名前を覚えたまま会話を続けられます。
3. **署名検証によるセキュアなWebhook受信**
   * LINEからのリクエストを `X-Line-Signature` で検証し、なりすましリクエストを拒否します。

---

## 💻 開発環境・使用技術
* **サーバー:** さくらインターネット（スタンダードプラン / CGIモード）
* **バックエンド:** PHP 8.x
* **外部API:** LINE Messaging API (Webhook) / Claude API（Anthropic, `claude-haiku-4-5`）
* **データ保存:** サーバー上のJSONファイル（ユーザー別の会話履歴）

---

## ⚙️ セットアップ手順

このリポジトリには認証情報（実キー）は含まれていません。
動かすには、雛形をコピーして自分のキーを設定する必要があります。

1. **設定ファイルを作成する**

   ```bash
   cp config.sample.php config.php
   ```

2. **`config.php` に自分のキーを記入する**

   | 変数 | 取得場所 |
   | --- | --- |
   | `$accessToken` | LINE Developers コンソール > Messaging API設定 > チャネルアクセストークン |
   | `$channelSecret` | LINE Developers コンソール > チャネル基本設定 > チャネルシークレット |
   | `$claudeApiKey` | Anthropic コンソール（ https://console.anthropic.com/ ）> API Keys |
   | `$errorLogPath` | エラーログを出力したいサーバー上の絶対パス |
   | `$historyDir` | 会話履歴JSONを保存するサーバー上のディレクトリの絶対パス |

3. **サーバーに `index.php` と `config.php` を配置する**
   * `config.php` は `.gitignore` で除外されているため、サーバーには手動でアップロードします。
   * `$historyDir` のディレクトリは初回アクセス時に自動作成されますが、書き込み権限が必要です。

4. **LINE側のWebhook URLに `index.php` のURLを設定する**

---

## 💡 技術的なこだわり・PHPの原理原則の適用

フロントエンド開発（JavaScript/React）の経験を活かしつつ、バックエンド特有の「サーバー環境の差異」や「セキュリティ」を意識して実装しました。

### 1. サーバー環境（CGIモード）に依存しないヘッダー取得
当初、LINEからの署名検証用に `getallheaders()` を使用していましたが、さくらサーバーのCGIモードの特性により関数未定義エラー（503 Service Unavailable）を引き起こすことが判明しました。
環境依存を排除するため、PHPのグローバル変数である `$_SERVER['HTTP_X_LINE_SIGNATURE']` から直接ヘッダーを取得する堅牢なコードへ修正し、安定稼働を実現しました。

### 2. cURL通信のハングアップ防止（503エラー対策）
LINEサーバーやClaude APIへ通信する際、バックエンド間通信（cURL）がハングアップしてサーバープロセスを逼迫させないよう、`curl_setopt($ch, CURLOPT_TIMEOUT, ...)` によるタイムアウト安全弁を実装しました。

### 3. 会話履歴によるコンテキスト保持
ステートレスなWebhookでも文脈のある会話ができるよう、ユーザーIDごとに会話履歴をJSONファイルへ永続化しています。
ファイル名にはユーザーIDをサニタイズ（`preg_replace` で英数字・ハイフン・アンダースコア以外を除去）した値を使い、不正なパス操作を防いでいます。また直近20件に絞ることで、ファイル肥大化とAPIトークン消費を抑えています。

### 4. セキュリティと環境変数の配慮
GitHubへのソースコード公開にあたり、LINE Messaging APIの各トークンや Claude API キーなどの機密情報（クレデンシャル）が生の文字列のままリポジトリに残らないよう、設定ファイル（`config.php`）に分離し `.gitignore` で除外しています。
公開リポジトリには雛形（`config.sample.php`）のみを含め、実キーは本番環境のサーバー内の `config.php` にのみ配置する構成としました。

---

## 📁 フォルダ構成
```text
.
├── index.php           # LINE Webhookのメインロジック（受信・署名検証・Claude呼び出し・返信・履歴管理）
├── config.php          # 認証情報（実キー）。.gitignoreで除外（リポジトリには含まれない）
├── config.sample.php   # 設定ファイルの雛形（これをコピーしてconfig.phpを作る）
├── .gitignore          # config.php などをGit管理から除外する設定
└── README.md           # 本ドキュメント
```
