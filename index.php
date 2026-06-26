<?php
// 1. 認証情報・環境依存の設定を読み込む
//    $accessToken / $channelSecret / $claudeApiKey / $errorLogPath / $historyDir がここで定義される
//    config.php は .gitignore で除外され GitHub には上がらない（雛形は config.sample.php）
require __DIR__ . '/config.php';

// 2. エラーログの設定
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $errorLogPath);

// 3. LINEからのデータ取得
$input = file_get_contents('php://input');

// 4. 署名検証
$signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
$hash = base64_encode(hash_hmac('sha256', $input, $channelSecret, true));
if ($signature !== $hash) {
    http_response_code(400);
    exit();
}

$data = json_decode($input, true);

if (!empty($data['events'])) {
    foreach ($data['events'] as $event) {
        if ($event['type'] === 'message' && $event['message']['type'] === 'text') {
            $userMessage = $event['message']['text'];
            $replyToken  = $event['replyToken'];
            $userId      = $event['source']['userId'];

            // 会話履歴を取得
            $history = getHistory($userId, $historyDir);

            // Claude APIを呼び出す（履歴つき）
            $aiResponse = callClaudeAPI($userMessage, $claudeApiKey, $history);

            // 履歴を更新して保存
            $history[] = ['role' => 'user',      'content' => $userMessage];
            $history[] = ['role' => 'assistant',  'content' => $aiResponse];
            saveHistory($userId, $history, $historyDir);

            // LINEへの返信
            $responseMessage = [
                'replyToken' => $replyToken,
                'messages'   => [
                    [
                        'type' => 'text',
                        'text' => $aiResponse
                    ]
                ]
            ];

            $ch = curl_init('https://api.line.me/v2/bot/message/reply');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($responseMessage));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}

http_response_code(200);


// ========================================
// 会話履歴の読み込み
// ========================================
function getHistory($userId, $historyDir) {
    // ディレクトリがなければ作成
    if (!is_dir($historyDir)) {
        mkdir($historyDir, 0755, true);
    }

    $file = $historyDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $userId) . '.json';
    if (!file_exists($file)) {
        return [];
    }

    $history = json_decode(file_get_contents($file), true);
    return is_array($history) ? $history : [];
}

// ========================================
// 会話履歴の保存（直近20件に絞る）
// ========================================
function saveHistory($userId, $history, $historyDir) {
    // 直近20件（10往復分）だけ保持
    $history = array_slice($history, -20);

    $file = $historyDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $userId) . '.json';
    file_put_contents($file, json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// ========================================
// Claude API呼び出し（履歴つき）
// ========================================
function callClaudeAPI($prompt, $apiKey, $history = []) {
    $url = 'https://api.anthropic.com/v1/messages';

    // 履歴に今回のメッセージを追加
    $messages   = $history;
    $messages[] = ['role' => 'user', 'content' => $prompt];

    $requestData = [
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 300,
        'system'     => implode("\n", [
            'あなたは親しみやすい占い師のLINEボットです。',
            '',
            '【基本ルール】',
            '- ユーザーが教えてくれた生年月日・性別・名前などは必ず覚えて、会話の中で積極的に活用してください。',
            '- 占い（星座・数秘術・九星気学・血液型など）の知識を使って、具体的で前向きなアドバイスをしてください。',
            '- 返答は1〜3文程度の短文でまとめてください。長々とした説明は不要です。',
            '- 敬語は使わず、友達に話すような親しみやすいトーンで話してください。',
            '- 同じ挨拶や定型文を繰り返さないでください。',
        ]),
        'messages' => $messages,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
        'content-type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return 'ごめん、ちょっと調子悪いみたい。もう一回話しかけてみて！';
    }

    $result = json_decode($response, true);

    if (!empty($result['content'][0]['text'])) {
        return trim($result['content'][0]['text']);
    }

    // デバッグ用：エラー内容をそのまま返す
    return $response;
}
