<?php

namespace Controllers;

use Helpers\ResponseFormatHelper;
use Redis;

class MessageController
{

    const NO_DELETED_STATUS = 0;
    const DELETED_STATUS = 1;
    const EDITED_STATUS = 2;

    const WRITE = 1;
    const NO_WRITE = 0;


    private $redis;


    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);

    }


    public function create(array $data)
    {

        // у каждого юзера есть counter сообщений
        $userId = $data['user_id'];
        $chatId = $data['chat_id'];

        $this->redis->incrBy("user:message:{$userId}", 1);
        $messageId = $this->redis->get("user:message:{$userId}");

        $messageTime = time();

        // добаляем сообщение в redis
        $this->redis->hSet("message:$userId:$messageId", 'user_id', $data['user_id']);
        $this->redis->hSet("message:$userId:$messageId", 'text', $data['text']);
        $this->redis->hSet("message:$userId:$messageId", 'chat_id', $data['chat_id']);
        $this->redis->hSet("message:$userId:$messageId", 'status', self::NO_WRITE);
        $this->redis->hSet("message:$userId:$messageId", 'time', $messageTime);
        // добавляем сообщение в чат

        $this->redis->incrBy("chat:unwrite:count:{$chatId}",1);

        var_dump("message:$userId:$messageId" . " sad test");


        $this->redis->zAdd("chat:{$chatId}", ['NX'], time(), "message:$userId:$messageId");


        // Если количество сообщений в чате больше чем AVAILABLE_COUNT_MESSAGES_IN_REDIS то самое раннее сообщение удаляется

//        if ($this->redis->zCount("chat:{$chatId}",'-inf','+inf') > ChatController::AVAILABLE_COUNT_MESSAGES_IN_REDIS) {
//            $firstMessage = $this->redis->zRange("chat:{$chatId}",0,0)[0];
//            $this->redis->zRem("chat:{$chatId}",$firstMessage);
//        }


        // добавляем сообщение в общий список сообщений

        $this->redis->zAdd('all:messages', ['NX'],self::NO_WRITE, "message:$userId:$messageId");

        // ставим последнее время для фильтрации чатов в списке пользователя
        $this->redis->zAdd("user:chats:{$userId}", ['NX'], time(), $chatId);


        $notifyUsers = $this->redis->zRangeByScore("chat:members:{$data['chat_id']}", 0, 3);

        foreach ($notifyUsers as $notifyUser) {
            $this->redis->zAdd("user:chats:{$notifyUser}", ['XX'], time(), $chatId);
        }
        //временно для приватных чатов
        $anotherUserId = array_diff($notifyUsers, [$userId]);
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
                'avatar' => $this->redis->get("user:avatar:{$userId}"),
                'user_name' => $this->redis->get("user:name:{$userId}"),
                'chat_name' => $this->redis->get("user:name:{$anotherUserId}"),
            ],
            // удаляю из оповещения по сокету самого пользователя который отправил сообщение
            'notify_users' => $notifyUsers,
        ];

    }

    public function write(array $data)
    {
        $messageId = $data['message_id'];
        $chatId = $data['chat_id'];
        $notifyUsers = $this->redis->zRange("chat:members:{$chatId}", 0, -1);

        $this->redis->hSet($data['message_id'], 'status', MessageController::WRITE);
        $messageOwner = $this->redis->hGet($data['message_id'], 'user_id');

        $this->redis->incrBy("chat:unwrite:count:{$chatId}",-1);
        $this->redis->zAdd("chat:{$chatId}", ['CH'], MessageController::WRITE, "message:$messageOwner:$messageId");

        return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers, [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'owner_id' => $messageOwner,
            'write' => strval(MessageController::WRITE),
        ]);


    }


}