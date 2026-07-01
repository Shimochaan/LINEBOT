<?php
/**
 * 認証情報・環境依存の設定ファイル（雛形）
 *
 * このファイル（config.sample.php）をコピーして config.php を作成し、
 * 各値を実際のものに書き換えてください。
 *
 *   cp config.sample.php config.php
 *
 * config.php は .gitignore で除外されており、GitHub には上がりません。
 * 実際の値（実キー・DBパスワード等）は本番サーバー上の config.php にのみ記述します。
 */

// ==========================================================
// LINE Messaging API
// ==========================================================
// チャネルアクセストークン
// LINE Developers コンソール > Messaging API設定 > チャネルアクセストークン
$accessToken = 'YOUR_CHANNEL_ACCESS_TOKEN';

// チャネルシークレット（署名検証に使用）
// LINE Developers コンソール > チャネル基本設定 > チャネルシークレット
$channelSecret = 'YOUR_CHANNEL_SECRET';

// ==========================================================
// Claude API（Anthropic）
// ==========================================================
// APIキー … https://console.anthropic.com/ > API Keys で発行
$claudeApiKey = 'YOUR_CLAUDE_API_KEY';

// ==========================================================
// パス設定（サーバーの絶対パス）
// ==========================================================
// PHPのエラーログ出力先
$errorLogPath = '/path/to/your/php_error.log';

// 会話履歴JSONを保存するディレクトリ
$historyDir = '/path/to/your/history';

// ==========================================================
// DB接続情報（アクティビティ記録・集計に使用）
// ==========================================================
$db_host = 'your-mysql-host';       // 例: mysqlXXXX.db.sakura.ne.jp
$db_name = 'your-database-name';
$db_user = 'your-database-user';
$db_pass = 'YOUR_DB_PASSWORD';

// ==========================================================
// 挙動の設定
// ==========================================================
// 会話履歴として保持する往復数（1往復 = user + assistant の2件）
$historyTurns = 6;
