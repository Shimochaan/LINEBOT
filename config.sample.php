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
 * 実際の値は本番サーバー上の config.php にのみ記述します。
 */

// LINE Messaging API のチャネルアクセストークン
// LINE Developers コンソール > Messaging API設定 > チャネルアクセストークン
$accessToken = 'YOUR_CHANNEL_ACCESS_TOKEN';

// LINE Messaging API のチャネルシークレット（署名検証に使用）
// LINE Developers コンソール > チャネル基本設定 > チャネルシークレット
$channelSecret = 'YOUR_CHANNEL_SECRET';

// Claude API（Anthropic）の APIキー
// https://console.anthropic.com/ > API Keys で発行
$claudeApiKey = 'YOUR_CLAUDE_API_KEY';

// エラーログの出力先（サーバーの絶対パス）
$errorLogPath = '/path/to/your/php_error.log';

// 会話履歴の保存先ディレクトリ（サーバーの絶対パス）
$historyDir = '/path/to/your/history';
