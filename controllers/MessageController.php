<?php

namespace Controllers;

use Helpers\MessageHelper;
use Helpers\ResponseFormatHelper;
use Patterns\MessageFactory\Factory;
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
        $data['message_type'] = $data['message_type'] ?? MessageHelper::TEXT_MESSAGE_TYPE;

        $this->redis->incrBy("user:message:{$userId}", 1);
        $messageId = $this->redis->get("user:message:{$userId}");

        $messageRedisKey = "message:$userId:$messageId";

        $data['message_time'] = time();


        // добаляем сообщение в redis
        MessageHelper::create($this->redis,$data,$messageRedisKey);

        // добавляем дополнительные параметры в зависимости от типа через фабрику

        $messageClass = Factory::getItem($data['message_type']);

        $messageClass->addExtraFields($this->redis,$messageRedisKey,$data);

        // добавляем сообщение в чат

        MessageHelper::addMessageInChat($this->redis,$chatId,$messageRedisKey);

        // Если количество сообщений в чате больше чем AVAILABLE_COUNT_MESSAGES_IN_REDIS то самое раннее сообщение удаляется

        MessageHelper::cleanFirstMessageInRedis($this->redis,$chatId);


        // добавляем сообщение в общий список сообщений

        $this->redis->zAdd('all:messages', ['NX'],self::NO_WRITE, "message:$userId:$messageId");

        // ставим последнее время для фильтрации чатов в списке пользователя
        $this->redis->zAdd("user:chats:{$userId}", ['NX'], $data['message_time'], $chatId);


        $notifyUsers = $this->redis->zRangeByScore("chat:members:{$data['chat_id']}", 0, 3);

        foreach ($notifyUsers as $notifyUser) {
            $this->redis->zAdd("user:chats:{$notifyUser}", ['XX'], $data['message_time'], $chatId);
        }

        return [
            'data' => $messageClass->returnResponseDataForCreateMessage($data,$messageRedisKey,$this->redis),
            'notify_users' => $notifyUsers,
        ];

    }

    public function write(array $data)
    {
        $messageId = $data['message_id'];
        $chatId = $data['chat_id'];
        $notifyUsers = $this->redis->zRange("chat:members:{$chatId}", 0, -1);

        $this->redis->hMSet($data['message_id'], ['status' => MessageHelper::MESSAGE_WRITE_STATUS]);
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