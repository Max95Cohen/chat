<?php

use Controllers\ChatController;
use Illuminate\Database\Capsule\Manager;

require __DIR__ . '/vendor/autoload.php';

$capsule = new \Illuminate\Database\Capsule\Manager();
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'chat',
    'username'  => 'admin',
    'password'  => '123',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_520_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();



$db = new \Illuminate\Database\Capsule\Manager();


$startDate = strtotime('01-01-2020 00:00:00');

$messages = \Illuminate\Database\Capsule\Manager::table('messages_old')->where('created_at','>=',$startDate)->get();
$insertMessageData = [];
$redis = new Redis();
$redis->connect('127.0.0.1',6379);

foreach ($messages as $message) {
    dump($message->id);

    if ($message->group_id == 0) {

        $chatId = $redis->get("private:{$message->from_id}:$message->to_id");
        $chatId = $chatId == false ? $redis->get("private:{$message->to_id}:$message->from_id") : $chatId;
        dump($chatId .'-> chat_id');
        if (!$chatId) {
            $chatId = Manager::table('chats')->insertGetId([
                'owner_id' => $message->from_id,
                'name' => null,
                'type' => ChatController::PRIVATE,
                'members_count' => 2,
            ]);
            $redis->set("private:{$message->from_id}:$message->to_id",$chatId);
            $redis->set("private:{$message->to_id}:$message->from_id",$chatId);
            $redis->zAdd("user:chats:{$message->from_id}",['NX'],$message->created_at,$chatId);
            $redis->zAdd("user:chats:{$message->to_id}",['NX'],$message->created_at,$chatId);

            Manager::table('chat_members')->insert([
                'user_id' => $message->from_id,
                'chat_id' => $chatId,
                'role' => ChatController::OWNER,
            ]);
            Manager::table('chat_members')->insert([
                'user_id' => $message->to_id,
                'chat_id' => $chatId,
                'role' => ChatController::OWNER,
            ]);

        }
        Manager::table('messages')->insert([
            'text' => strval($message->message),
            'status' => $message->deleted == 0 ? 3 : $message->status,
            'chat_id' => $chatId,
            'user_id' => $message->from_id,
            'time' => $message->created_at,
        ]);


    }


}

$chats = Manager::table('chats')->get();

foreach ($chats as $chat) {
    dump($chat->id);
    $chatMessages = Manager::table('messages')->where('chat_id',$chat->id)->get();

    if (count($chatMessages) > 40) {
        $redisMessages =$chatMessages->sortByDesc('time')->take(40);

        foreach ($redisMessages as $redisMessage) {
            $redis->zAdd("chat:{$chat->id}",'[NX]',$redisMessage->time,$redisMessage->id);

            $redis->hSet("message:{$redisMessage->id}",'user_id',$redisMessage->user_id);
            $redis->hSet("message:{$redisMessage->id}",'text',$redisMessage->text);
            $redis->hSet("message:{$redisMessage->id}",'chat_id',$redisMessage->chat_id);
            $redis->hSet("message:{$redisMessage->id}",'time',$redisMessage->time);
        }

    }elseif(count($chatMessages) < 40 && count($chatMessages) > 0){
        $redisMessages = Manager::table('messages')
            ->orderByDesc('time')
            ->where('chat_id',$chat->id)
            ->get();

        foreach ($redisMessages as $redisMessage) {1
            $redis->zAdd("chat:{$chat->id}",'[NX]',$redisMessage->time,$redisMessage->id);

            $redis->hSet("message:{$redisMessage->id}",'user_id',$redisMessage->user_id);
            $redis->hSet("message:{$redisMessage->id}",'text',$redisMessage->text);
            $redis->hSet("message:{$redisMessage->id}",'chat_id',$redisMessage->chat_id);
            $redis->hSet("message:{$redisMessage->id}",'time',$redisMessage->time);

        }
    }

}






