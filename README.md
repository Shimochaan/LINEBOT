# 🔮 おみくじENJINボット (LINE Messaging API ✕ PHP)

ユーザーがLINEで送ったメッセージに応じて、オウム返しやランダムな運勢を返却するLINE Botアプリケーションです。
さくらインターネット（レンタルサーバー）の環境特性を考慮し、セキュアかつ安定して稼働するバックエンドロジックをPHPで構築しました。

## 🚀 成果物URL
* **LINE公式アカウント（友だち追加QRコード/URL）**
  * <img width="360" height="360" alt="image" src="https://github.com/user-attachments/assets/042f2bfb-5b56-4cf8-92ab-4f14717e02f0" />


---

## 🛠️ 主な機能
1. **インテリジェント・オウム返し**
   * 「おみくじ」以外のテキストが送られてきた場合、ユーザーのメッセージをオウム返ししつつ、「おみくじ」と送信するよう促す案内文を自動返却します。
2. **ランダムおみくじ機能**
   * ユーザーが「おみくじ」と送信すると、PHPの配列からランダム（大吉・中吉・吉・小吉・凶）に運勢を抽出し、占い結果を返却します。

---

## 💻 開発環境・使用技術
* **サーバー:** さくらインターネット（スタンダードプラン / CGIモード）
* **バックエンド:** PHP 8.x
* **外部API:** LINE Messaging API (Webhook)

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
   | `$errorLogPath` | エラーログを出力したいサーバー上の絶対パス |

3. **サーバーに `index.php` と `config.php` を配置する**
   * `config.php` は `.gitignore` で除外されているため、サーバーには手動でアップロードします。

4. **LINE側のWebhook URLに `index.php` のURLを設定する**

---

## 💡 技術的なこだわり・PHPの原理原則の適用

フロントエンド開発（JavaScript/React）の経験を活かしつつ、バックエンド特有の「サーバー環境の差異」や「セキュリティ」を意識して実装しました。

### 1. サーバー環境（CGIモード）に依存しないヘッダー取得
当初、LINEからの署名検証用に `getallheaders()` を使用していましたが、さくらサーバーのCGIモードの特性により関数未定義エラー（503 Service Unavailable）を引き起こすことが判明しました。
環境依存を排除するため、PHPのグローバル変数である `$_SERVER['HTTP_X_LINE_SIGNATURE']` から直接ヘッダーを取得する堅牢なコードへ修正し、安定稼働を実現しました。

### 2. cURL通信のハングアップ防止（503エラー対策）
LINEサーバーへメッセージを返信する際、バックエンド間通信（cURL）がハングアップしてサーバープロセスを逼迫させないよう、`curl_setopt($ch, CURLOPT_TIMEOUT, 10);` による10秒のタイムアウト安全弁を実装しました。

### 3. セキュリティと環境変数の配慮
GitHubへのソースコード公開にあたり、LINE Messaging APIの `Channel Access Token` および `Channel Secret` などの機密情報（クレデンシャル）が生の文字列のままリポジトリに残らないよう、設定ファイル（`config.php`）に分離し `.gitignore` で除外しています。
公開リポジトリには雛形（`config.sample.php`）のみを含め、実トークンは本番環境のサーバー内の `config.php` にのみ配置する構成としました。

---

## 📁 フォルダ構成
```text
.
├── index.php           # LINE Webhookのメインロジック（リクエスト受信・解析・返信処理）
├── config.php          # 認証情報（実キー）。.gitignoreで除外（リポジトリには含まれない）
├── config.sample.php   # 設定ファイルの雛形（これをコピーしてconfig.phpを作る）
├── .gitignore          # config.php などをGit管理から除外する設定
└── README.md           # 本ドキュメント
```
