<?php
// ==========================================================
// 認証情報・環境設定を読み込む
// ==========================================================
require __DIR__ . '/config.php';

// ==========================================================
// エラー設定
// ==========================================================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $errorLogPath);

// ==========================================================
// LINEからのデータ取得 & 署名検証
// ==========================================================
$input = file_get_contents('php://input');

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
            $userMessage = trim($event['message']['text']);
            $replyToken  = $event['replyToken'];
            $userId      = $event['source']['userId'];

            // ==========================================================
            // 🔐 DB接続
            // ==========================================================
            try {
                $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);

                $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(64) NOT NULL,
                    activity_type VARCHAR(32) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (PDOException $e) {
                error_log("DB Connection Error: " . $e->getMessage());
                $pdo = null;
            }

            // ==========================================================
            // 🧭 意図を判定（順番が超重要：集計→記録 の順で見る）
            // ==========================================================
            $intent = detectIntent($userMessage);

            // ----------------------------------------------------------
            // 📊 集計
            // ----------------------------------------------------------
            if ($intent === 'summary') {
                if ($pdo) {
                    try {
                        $stmt = $pdo->prepare("SELECT activity_type, COUNT(*) as total
                            FROM activity_logs
                            WHERE user_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
                            GROUP BY activity_type");
                        $stmt->execute([$userId]);
                        $rows = $stmt->fetchAll();

                        $saunaCount = 0;
                        $runCount = 0;
                        foreach ($rows as $row) {
                            if ($row['activity_type'] === 'sauna') $saunaCount = $row['total'];
                            if ($row['activity_type'] === 'run')   $runCount   = $row['total'];
                        }

                        $replyText  = "📊 今月のアクティビティ集計！\n\n";
                        $replyText .= "・サウナ： " . $saunaCount . " 回 🧖‍♂️\n";
                        $replyText .= "・ランニング： " . $runCount . " 回 🏃‍♂️\n\n";
                        $replyText .= "データベースからSQLで引っ張ってきたよ！最高！";

                        sendLineReplyPro($replyToken, $replyText, $accessToken);
                    } catch (PDOException $e) {
                        error_log("Select monthly error: " . $e->getMessage());
                        sendLineReplyPro($replyToken, "ごめん、集計に失敗しちゃった…あとでまた試してね！", $accessToken);
                    }
                } else {
                    sendLineReplyPro($replyToken, "ごめん、いまデータベースに繋がらないみたい。あとでまた試してね！", $accessToken);
                }
                http_response_code(200);
                exit();
            }

            // ----------------------------------------------------------
            // 🧖‍♂️ サウナ記録
            // ----------------------------------------------------------
            if ($intent === 'sauna') {
                if ($pdo) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type) VALUES (?, 'sauna')");
                        $stmt->execute([$userId]);
                        sendLineReplyPro($replyToken, "サウナの記録をデータベースにガシャコンと保存したよ！🧖‍♂️", $accessToken);
                    } catch (PDOException $e) {
                        error_log("Insert sauna error: " . $e->getMessage());
                        sendLineReplyPro($replyToken, "ごめん、保存に失敗しちゃった…もう一回試してみて！", $accessToken);
                    }
                } else {
                    sendLineReplyPro($replyToken, "ごめん、いまデータベースに繋がらないみたい。あとでまた試してね！", $accessToken);
                }
                http_response_code(200);
                exit();
            }

            // ----------------------------------------------------------
            // 🏃‍♂️ ラン記録
            // ----------------------------------------------------------
            if ($intent === 'run') {
                if ($pdo) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type) VALUES (?, 'run')");
                        $stmt->execute([$userId]);
                        sendLineReplyPro($replyToken, "ランニングの記録をデータベースにガシャコンと保存したよ！🏃‍♂️", $accessToken);
                    } catch (PDOException $e) {
                        error_log("Insert run error: " . $e->getMessage());
                        sendLineReplyPro($replyToken, "ごめん、保存に失敗しちゃった…もう一回試してみて！", $accessToken);
                    }
                } else {
                    sendLineReplyPro($replyToken, "ごめん、いまデータベースに繋がらないみたい。あとでまた試してね！", $accessToken);
                }
                http_response_code(200);
                exit();
            }

            // ----------------------------------------------------------
            // 🔮 それ以外は占い（会話履歴つき）
            // ----------------------------------------------------------
            $history = loadHistory($historyDir, $userId, $historyTurns);
            $messages = $history;
            $messages[] = ['role' => 'user', 'content' => $userMessage];

            $aiResponse = callClaudeAPIPro($messages, $claudeApiKey);

            $history[] = ['role' => 'user', 'content' => $userMessage];
            $history[] = ['role' => 'assistant', 'content' => $aiResponse];
            saveHistory($historyDir, $userId, $history, $historyTurns);

            sendLineReplyPro($replyToken, $aiResponse, $accessToken);
            http_response_code(200);
            exit();
        }
    }
}

http_response_code(200);

// ==========================================================
// 🧭 メッセージから意図を判定する
//    集計 → サウナ → ラン の優先順で判定する（ここが今回のキモ）
// ==========================================================
function detectIntent($msg) {
    // --- 1. まず「集計・質問」かどうかを先に判定する ---
    // 「今月何回サウナ行った？」のような "質問" を記録と誤判定しないため
    $summaryKeywords = ['集計', '今月の記録', '何回', 'まとめ', '教えて', '記録は', 'どれくらい', 'カウント'];
    foreach ($summaryKeywords as $kw) {
        if (mb_strpos($msg, $kw) !== false) {
            return 'summary';
        }
    }

    // --- 2. サウナ記録 ---
    // 「サウナ」を含み、かつ質問系でない（上で弾かれている）
    if (mb_strpos($msg, 'サウナ') !== false || mb_strpos($msg, 'ととの') !== false) {
        return 'sauna';
    }

    // --- 3. ラン記録 ---
    // 「ラン」だけでなく「走った」等の表現もカバー
    $runKeywords = ['ラン', 'ランニング', '走っ', 'ジョギング', 'ジョグ', 'run'];
    foreach ($runKeywords as $kw) {
        if (mb_strpos($msg, $kw) !== false) {
            return 'run';
        }
    }

    // --- 4. どれにも当てはまらなければ占い ---
    return 'fortune';
}

// ==========================================================
// 🛠️ LINE返信用の共通関数
// ==========================================================
function sendLineReplyPro($replyToken, $text, $accessToken) {
    $responseMessage = [
        'replyToken' => $replyToken,
        'messages'   => [['type' => 'text', 'text' => $text]]
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

// ==========================================================
// 🔮 Claude API呼び出し関数（messages配列を受け取る）
// ==========================================================
function callClaudeAPIPro($messages, $apiKey) {
    $url = 'https://api.anthropic.com/v1/messages';
    $requestData = [
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 300,
        'system'     => "あなたは親しみやすい占い師のLINEボットです。友達に話すようなトーンで、前向きなアドバイスを1〜3文の短文で返してください。過去の会話の流れも踏まえて返答してください。",
        'messages'   => $messages,
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
    return 'ちょっと星の配置が乱れてるみたい。時間を置いてまた話しかけてね！';
}

// ==========================================================
// 💾 会話履歴：読み込み
// ==========================================================
function loadHistory($dir, $userId, $turns) {
    $file = historyFilePath($dir, $userId);
    if (!is_file($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $arr = json_decode($json, true);
    if (!is_array($arr)) {
        return [];
    }
    return array_slice($arr, -1 * $turns * 2);
}

// ==========================================================
// 💾 会話履歴：保存
// ==========================================================
function saveHistory($dir, $userId, $history, $turns) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        error_log("History dir not writable: " . $dir);
        return;
    }
    $history = array_slice($history, -1 * $turns * 2);
    $file = historyFilePath($dir, $userId);
    file_put_contents($file, json_encode($history, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ==========================================================
// 💾 ユーザーIDから安全なファイルパスを生成
// ==========================================================
function historyFilePath($dir, $userId) {
    $safe = hash('sha256', $userId);
    return rtrim($dir, '/') . '/' . $safe . '.json';
}
