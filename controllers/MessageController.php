<?php

namespace Controllers;

use Helpers\ResponseFormatHelper;
use Redis;

class MessageController
{
    private $r;

    const NO_DELETED_STATUS = 0;
    const DELETED_STATUS = 1;
    const EDITED_STATUS = 2;

    const WRITE = 1;
    const NO_WRITE = 0;

    public function create(array $data)
    {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);

        // у каждого юзера есть counter сообщений
        $userId = $data['user_id'];
        $chatId = $data['chat_id'];

        $messageId = $redis->incrBy("user:message:{$userId}", 1);
        $messageTime = time();

        // добаляем сообщение в redis
        $redis->hSet("message:$userId:$messageId", 'user_id', $data['user_id']);
        $redis->hSet("message:$userId:$messageId", 'text', $data['text']);
        $redis->hSet("message:$userId:$messageId", 'chat_id', $data['chat_id']);
        $redis->hSet("message:$userId:$messageId", 'status', self::NO_WRITE);
        $redis->hSet("message:$userId:$messageId", 'time', $messageTime);
        // добавляем сообщение в чат

        $redis->zAdd("chat:{$data['chat_id']}", ['NX'], time(),"message:$userId:$messageId");
        // добавляем сообщение в общий список сообщений
        $redis->zAdd('all:messages', ['NX'], "message:$userId:$messageId", self::NO_DELETED_STATUS);
        // ставим последнее время для фильтрации чатов в списке пользователя
        $redis->zAdd("user:chats:{$userId}",['NX'],time(),$chatId);


        $notifyUsers = $redis->zRangeByScore("chat:members:{$data['chat_id']}",0,3);

        foreach ($notifyUsers as $notifyUser) {
            $redis->zAdd("user:chats:{$notifyUser}", ['XX'],time(), $chatId);
        }
        //временно для приватных чатов
        $anotherUserId = array_diff($notifyUsers,[$userId]);
        $anotherUserId = array_shift($anotherUserId);


        return [
            'data' => [
                'status' => 'true',
                'write' => MessageController::NO_WRITE,
                'text' => $data['text'],
                'chat_id' => $chatId,
                'message_id' => "message:$userId:$messageId",
                'user_id' => $userId,
                'time' => $messageTime,
                'avatar' => $redis->get("user:avatar:{$userId}"),
                'user_name' => $redis->get("user:name:{$userId}"),
                'chat_name' => $redis->get("user:name:{$anotherUserId}"),
            ],
            // удаляю из оповещения по сокету самого пользователя который отправил сообщение
            'notify_users' => $notifyUsers,
        ];

    }

    public function write(array $data) {
        $redis = new Redis();
        $redis->connect('127.0.0.1',6379);
        $messageId = $data['message_id'];
        $chatId = $data['chat_id'];
        $notifyUsers = $redis->zRange("chat:members:{$chatId}",0,-1);

        $redis->hSet($data['message_id'],'status',MessageController::WRITE);
        $messageOwner = $redis->hGet($data['message_id'],'user_id');


        return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers,[
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'owner_id' =>$messageOwner,
            'write' => strval(MessageController::WRITE),
        ]);


    }



}