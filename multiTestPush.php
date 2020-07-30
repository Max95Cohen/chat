<?php

use Helpers\ConfigHelper;
use Helpers\MessageHelper;
use Illuminate\Database\Capsule\Manager;

require __DIR__ . '/vendor/autoload.php';


$capsule = new Manager();

$config = ConfigHelper::getDbConfig('chat_db');

$capsule->addConnection([
    'driver' => $config['driver'],
    'host' => $config['host'],
    'database' => $config['database'],
    'username' => $config['username'],
    'password' => $config['password'],
    'charset' => $config['charset'],
    'prefix' => $config['prefix'],
    'collation' => $config['collation'],
]);

$capsule->setAsGlobal();
$fireBaseApiKey = 'AAAAvEEv5W0:APA91bG0qVzveg4pOsnyf6jm3Jj5NuA3_Q37hBF_rqYX59zAtUQpF1qMUSgKIdgs9JRBwkdZ58vBhCF_DEhNSE_OOn1-oox0Zos6cxsi5wxR22CqPvrqxHbTMg0nLo6AlZZoHhCc7J4w';
$fireBaseUrl = 'https://fcm.googleapis.com/fcm/send';

while (true) {
    $redis = new Redis();

    $redis->pconnect('127.0.0.1',6379);
    $allNotify = $redis->zRange("all:notify:queue", 0, -1);

    foreach ($allNotify as $notify) {

        $notifyData = $redis->hGetAll($notify);
        $notifies = [];

        if ($notifyData) {
            $message = $redis->hGetAll($notifyData['link']);

            if ($message) {
                $chat = Manager::table('chats')->find($message['chat_id']);
                $chatMembers = $redis->zRangeByScore("chat:members:{$message['chat_id']}", 0, "+inf");

                $attachments = $message['attachments'] ?? null;
                $messageText = $attachments ? MessageHelper::getAttachmentTypeString($message['type']) : $message['text'];


                if ($chat && is_array($chatMembers)) {

                    $chatName = $chat->name ?? $redis->get("user:name:{$message['user_id']}");
                    $userName = $redis->get("user:name:{$message['user_id']}");
                    $mh = curl_multi_init();

                    foreach ($chatMembers as $k => $chatMember) {
                        $messUserId = $message['user_id'];
                        dump($messUserId,$chatMember);
                        if ($messUserId == $chatMember) {
                            continue;
                        }
                        $memberFcmToken = $redis->hGet("Customer:{$chatMember}", 'fcm');
                        $ch = curl_init();

                        $postData = json_encode([
                            'collapse_key' => 'type_a',
                            'data' => [
                                'user_name' => $userName,
                                'chat_name' =>$chatName,
                                'chat_id' => $message['chat_id'],
                                'type' => $message['type'] ?? MessageHelper::TEXT_MESSAGE_TYPE,
                                'avatar' => $redis->get("user:avatar"),
                                'user_id' => $message['user_id'],
                            ],
                            'priority' => 'high',
                            'to' => $memberFcmToken,
                            'notification' => [
                                'body' => $messageText,
                                'title' => $chatName,
                                'sound' => 'default',
                            ],
                        ]);
                        curl_setopt($ch, CURLOPT_URL, $fireBaseUrl);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'Authorization: key=' . $fireBaseApiKey,
                        ]);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


                        $notifies[$k] = $ch;

                        curl_multi_add_handle($mh, $notifies[$k]);
                    }

                    $active = null;

                    do {
                        curl_multi_exec($mh, $active);
                    } while ($active);


                    foreach (array_keys($notifies) as $key) {
                        $error = curl_error($notifies[$key]);
                        $requestUri = curl_getinfo($notifies[$key], CURLINFO_EFFECTIVE_URL);
                        $time = curl_getinfo($notifies[$key], CURLINFO_TOTAL_TIME);
                        $response = curl_multi_getcontent($notifies[$key]);  // get results
                        if (!empty($error)) {
                            echo "The request $key return a error: $error" . "\n";
                        } else {
                            echo "The request to '$requestUri' returned '$response' in $time seconds." . "\n";
                        }

                        curl_multi_remove_handle($mh, $notifies[$key]);
                    }

                    curl_multi_close($mh);


                }

            }


        }

        $redis->del($notify);
        $redis->zRem('all:notify:queue', $notify);
    }

}
