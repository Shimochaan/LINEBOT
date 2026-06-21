<?php

// 1. 認証情報・環境依存の設定を読み込む（実キーは config.php 内のみ／Gitには上げない）
//    $accessToken / $channelSecret / $errorLogPath がここで定義される
require __DIR__ . '/config.php';

// 2. エラーが発生した際、画面ではなくログファイルに記録する設定
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $errorLogPath);

// 3. LINE側から送られてきたデータ（Webhook）を取得
$input = file_get_contents('php://input');

// 4. 安全対策（署名検証）：$_SERVERを使ってサーバー環境に依存せずヘッダーを取得
$signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
$hash = base64_encode(hash_hmac('sha256', $input, $channelSecret, true));

// LINEの検証ボタン（ダミーデータ）や不正アクセスの場合はここでブロックする
if ($signature !== $hash) {
    http_response_code(400);
    exit();
}

// 5. 生データ（JSON文字列）を、PHPの配列に変換
$data = json_decode($input, true);

// 6. 届いたデータの中に「メッセージイベント」があるか確認
if (!empty($data['events'])) {
    foreach ($data['events'] as $event) {

        // メッセージタイプが「テキスト」の場合のみ処理する
        if ($event['type'] === 'message' && $event['message']['type'] === 'text') {

            // ユーザーが送ってきた文字と、返信用トークンを取得
            $userMessage = $event['message']['text'];
            $replyToken = $event['replyToken'];

            // 💡【新機能】おみくじの条件分岐ロジック
            if ($userMessage === 'おみくじ') {
                // おみくじの結果を配列で用意
                $fortunes = [
                    '大吉！最高の1日になるよ！🌟',
                    '吉！手堅くハッピーな日。👍',
                    '中吉！サウナに行くと運気爆上がり！♨️',
                    '小吉！ボチボチいきましょう。🌱',
                    '凶！でも、筋トレすれば運気は上向く！💪'
                ];

                // 配列からランダムに1つのインデックス（部屋番号）を抽出
                $randomIndex = array_rand($fortunes);
                // 返信するテキストを決定
                $replyText = "🔮本日のおみくじ結果🔮\n\n" . $fortunes[$randomIndex];

            } else {
                // 「おみくじ」以外の言葉が送られてきた場合（オウム返し＋案内）
                $replyText = "「" . $userMessage . "」って言ったね！\n\n「おみくじ」って送信すると、今日の運勢が占えるよ！";
            }

            // LINEの返信データ（JSON）に組み立てる
            $responseMessage = [
                'replyToken' => $replyToken,
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => $replyText // 条件分岐で決まったテキストを入れる
                    ]
                ]
            ];

            // 7. cURLを使ってLINEのサーバーへ返信リクエストを送信
            $ch = curl_init('https://api.line.me/v2/bot/message/reply');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($responseMessage));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);

            // さくらサーバーで503エラー（タイムアウトハング）を防ぐための安全弁
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            // 実行
            $result = curl_exec($ch);
            curl_close($ch);
        }
    }
}

// LINEサーバーに対して正常終了（200 OK）を伝える
http_response_code(200);
