<?php

use Controllers\ChatController;
use Helpers\MessageHelper;
use Illuminate\Database\Capsule\Manager;
use Kreait\Firebase\Messaging\Notification;

require __DIR__ . '/../vendor/autoload.php';


$redis = new Redis();
$redis->pconnect('127.0.0.1', 6379);


$factory = (new \Kreait\Firebase\Factory())->withServiceAccount(__DIR__ . '/indigo24-fdf1b-firebase-adminsdk-upaae-5fb0aaf3a5.json');
$messaging = $factory->createMessaging();

$capsule = new Illuminate\Database\Capsule\Manager();

$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'chat',
    'username' => 'admin',
    'password' => '123',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();


//echo 'Successful sends: '.$sendReport->successes()->count().PHP_EOL;
//echo 'Failed sends: '.$sendReport->failures()->count().PHP_EOL;


while (true) {

    $allNotify = $redis->zRange("all:notify:queue", 0, -1);
    foreach ($allNotify as $notify) {

        $data = $redis->hGetAll($notify);

        $message = $redis->hGetAll($data['link']);
        $messageText = $message['attachments'] ? MessageHelper::getAttachmentTypeString($message['type']) : $message['text'];

        //@TODO хранить имена чатов в redis сегодня для теста пусть берет из mysql

        $chat = Manager::table('chats')->find($message['chat_id']);
        $chatMembers = $redis->zRangeByScore("chat:members:{$message['chat_id']}", 0, "+inf");
        if ($chat) {

            $chatName = $chat->name ?? $redis->get("user:name:{$message['user_id']}");

            $chatName = $chatName == false ? '' : $chatName;

            $userName = $redis->get("user:name:{$message['user_id']}");
            $badge = $redis->get("chat:unwrite:count:{$message['chat_id']}");

            $data = [
                'data' => [
                    'user_name' => $userName,
                    'chat_id' => $message['chat_id'],
                    'type' => $message['type'] ?? MessageHelper::TEXT_MESSAGE_TYPE,
                    'avatar' => $redis->get("user:avatar"),
                    'user_id' => $message['user_id'],
                ],
                'notification' => [
                    'title' =>$chatName,
                    'body' => $messageText,
                    'sound' => 'default',
                ],
            ];


            $messageForPush = \Kreait\Firebase\Messaging\CloudMessage::fromArray($data);

            // вычисляю device токены пользователей
            $deviceTokens = [];
            foreach ($chatMembers as $memberId) {

                $fcm = $redis->hGet("Customer:$memberId", 'fcm');
                if ($fcm) {
                    $deviceTokens[] = $fcm;
                }
            }
            $sendReport = $messaging->sendMulticast($messageForPush, $deviceTokens);
            dump($sendReport->successes()->count() . "успешно отправлено");
            dump($sendReport->failures()->count() . "неудачно отправлено");

        }
        // удаляю запись из очереди и hash таблицу из redis

        $redis->del($notify);
        $redis->zRem('all:notify:queue', $notify);

    }


}

