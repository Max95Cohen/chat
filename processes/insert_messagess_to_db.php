<?php

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

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




while (true) {

    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);

    $allMessages = $redis->zRange('all:messages', 0, -1);
    $redis->close();


    $messagesToChunk = array_chunk($allMessages, 1000);

    foreach ($messagesToChunk as $oneChunkMessages) {

        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);


        $oneChunkInsertMessages = [];
        $oneChunkDeletedMessages = [];

        // собирает массив для записи  в mysql
        foreach ($oneChunkMessages as $message) {
            $messageData = $redis->hGetAll($message);

            if ($messageData) {
                $oneChunkInsertMessages[] = [
                    'user_id' => intval($messageData['user_id']),
                    'text' => $messageData['text'],
                    'chat_id' => $messageData['chat_id'],
                    'status' => $messageData['status'],
                    'time' => intval($messageData['time']),
                    'redis_id' => $message
                ];

                $oneChunkDeletedMessages[] = $message;

            }

        }

        DB::table('messages')->insert($oneChunkInsertMessages);

        // удаляет сообщения из общей очереди redis также удаляет их hash table

        foreach ($oneChunkDeletedMessages as $deletedMessage) {

            $redis->zRem('all:messages',$deletedMessage);

        }


    }
}

