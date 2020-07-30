<?php

namespace Controllers;

use Helpers\ChatHelper;
use Helpers\MediaHelper;
use Helpers\MessageHelper;
use Helpers\ResponseFormatHelper;
use Illuminate\Database\Capsule\Manager;
use Patterns\MessageFactory\Factory;
use Redis;
use Traits\RedisTrait;

class MessageController
{

    const NO_DELETED_STATUS = 0;
    const DELETED_STATUS = 1;
    const EDITED_STATUS = 2;

    const WRITE = 1;
    const NO_WRITE = 0;

    use RedisTrait;

    /**
     * @param array $data
     * @return array
     */
    public function create(array $data)
    {
        // у каждого юзера есть counter сообщений
        $userId = $data['user_id'];
        $chatId = $data['chat_id'];

        $this->redis->incrBy("user:message:{$userId}", 1);
        $messageId = $this->redis->get("user:message:{$userId}");


        $data['message_type'] = $data['message_type'] ?? MessageHelper::TEXT_MESSAGE_TYPE;

        $messageRedisKey = "message:$userId:$messageId";

        $data['message_time'] = time();


        // добаляем сообщение в redis
        MessageHelper::create($this->redis, $data, $messageRedisKey);

        // добавляем дополнительные параметры в зависимости от типа через фабрику

        $messageClass = Factory::getItem($data['message_type']);

        // @TODO максимально тупой временный if (выпуск приложение через пару часов) ненавижу фротов
        $fileId = $data['file_id'] ?? null;

        if ($fileId) {
            $data['mime_type'] = Manager::table('user_uploads')->where('id',$fileId)->value('mime_type');
        }

        $messageClass->addExtraFields($this->redis, $messageRedisKey, $data);

        $data['message_type'] = $this->redis->hGet($messageRedisKey, 'type');
        // добавляем сообщение в чат

        MessageHelper::addMessageInChat($this->redis, $chatId, $messageRedisKey);

        // Если количество сообщений в чате больше чем AVAILABLE_COUNT_MESSAGES_IN_REDIS то самое раннее сообщение удаляется

        MessageHelper::cleanFirstMessageInRedis($this->redis, $chatId);


        // добавляем сообщение в общий список сообщений

        $this->redis->zAdd('all:messages', ['NX'], self::NO_WRITE, "message:$userId:$messageId");

        // ставим последнее время для фильтрации чатов в списке пользователя
        $this->redis->zAdd("user:chats:{$userId}", ['NX'], $data['message_time'], $chatId);


        $notifyUsers = $this->redis->zRangeByScore("chat:members:{$data['chat_id']}", 0, 3);

        foreach ($notifyUsers as $notifyUser) {
            $this->redis->zAdd("user:chats:{$notifyUser}", ['XX'], $data['message_time'], $chatId);
        }

        // здесь добавляю в очередь на отправку уведомлений!!!!@TODO отрефакторить это
        // пуш в новое приложение
        $this->redis->hSet("push:notify:{$userId}:{$messageId}", 'type', PushController::NOTIFY_CREATE_NEW_MESSAGE_IN_CHAT);
        $this->redis->hSet("push:notify:{$userId}:{$messageId}", 'link', "message:$userId:$messageId");

        // пуш в старое приложение
        $this->redis->hSet("push:notify:old:{$userId}:{$messageId}", 'type', PushController::NOTIFY_CREATE_NEW_MESSAGE_IN_CHAT);
        $this->redis->hSet("push:notify:old:{$userId}:{$messageId}", 'link', "message:$userId:$messageId");


        // очередь для пуша старый андроид и новый
        $this->redis->zAdd("all:notify:queue", ['NX'], time(), "push:notify:{$userId}:{$messageId}");
        $this->redis->zAdd("all:notify:old:android:queue", ['NX'], time(), "push:notify:old:{$userId}:{$messageId}");

        $messageClass = Factory::getItem($data['message_type']);

//        $messageClass->addExtraFields($this->redis,$messageRedisKey,$data);

        $responseData = $messageClass->returnResponseDataForCreateMessage($data, $messageRedisKey, $this->redis);
        $this->redis->close();

        return [
            'data' => $responseData,
            'notify_users' => $notifyUsers,
        ];

    }

    /**
     * @param array $data
     * @return array[]
     */
    public function write(array $data)
    {
        $messageId = $data['message_id'];
        $chatId = $data['chat_id'];
        $notifyUsers = $this->redis->zRange("chat:members:{$chatId}", 0, -1);

        $this->redis->hMSet($data['message_id'], ['status' => MessageHelper::MESSAGE_WRITE_STATUS]);
        $messageOwner = $this->redis->hGet($data['message_id'], 'user_id');

        $this->redis->incrBy("chat:unwrite:count:{$chatId}", -1);
        $this->redis->zAdd("chat:{$chatId}", ['CH'], MessageController::WRITE, "message:$messageOwner:$messageId");

        return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers, [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'owner_id' => $messageOwner,
            'write' => strval(MessageController::WRITE),
        ]);

    }


    public function twoVersionWrite(array $data)
    {
        $messageId = $data['message_id'];
        $chatId = $data['chat_id'];

        $chatMembers = ChatHelper::getChatMembers($chatId,$this->redis);


    }


    /**
     * @param array $data
     * @return array
     */
    public function edit(array $data): array
    {
        $data = Factory::getItem($data['message_type'])->editMessage($data, $this->redis);
        $notifyUsers = ChatHelper::getChatMembers((int)$data['chat_id'], $this->redis);
        $this->redis->close();

        return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers, $data);
    }

    /**
     * @param array $data
     * @return array
     */
    public function delete(array $data): array
    {
        $checkRedis = $this->redis->hGet($data['message_id'], 'type');

        $messageType = $checkRedis === false ? Manager::table('messages')->where('id', $data['message_id'])->value('type') : $checkRedis;

        $data = Factory::getItem($messageType)->deleteMessage($data, $this->redis);

        $this->redis->set("all:delete:{$data['message_id']}", 1);

        $notifyUsers = ChatHelper::getChatMembers((int)$data['chat_id'], $this->redis);
        $this->redis->close();

        return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers, $data);

    }

    /**
     * @param array $data
     * @return array
     */
    public function deleteSelf(array $data): array
    {
        $checkRedis = $this->redis->hGet($data['message_id'], 'type');

        $messageType = $checkRedis === false ? Manager::table('messages')->where('id', $data['message_id'])->value('type') : $checkRedis;

        $data = Factory::getItem($messageType)->deleteOne($data, $this->redis);

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $data);

    }


    public function forward(array $data)
    {
        $forwardChatId = $data['chat_id'];
        $userId = $data['user_id'];

        $messageIds = explode(',', $data['forward_messages_id']);
        // @TODO отрефакторить это собирать все id сообщений и делать 1 sql запрос пока пусть так для теста
        $responseData = [];
        foreach ($messageIds as $messageId) {
            $redisForwardMessageData = $this->redis->hGetAll($messageId);
            $messageData = $redisForwardMessageData == [] ? Manager::table('messages')->where('id', $messageId)->first()->toArray() : $redisForwardMessageData;
            $attachments = $messageData['attachments'] ?? null;
            if ($messageData) {
                // создать сообщение и добавить в чат

                $messageId = $this->redis->incrBy("user:message:{$userId}", 1);
                $this->redis->hSet($messageId, 'text', $messageData['text']);
                $this->redis->hSet($messageId, 'chat_id', $forwardChatId);
                $this->redis->hSet($messageId, 'user_id', $data['user_id']);
                $this->redis->hSet($messageId, 'status', MessageController::NO_WRITE);
                $this->redis->hSet($messageId, 'time', time());
                $this->redis->hSet($messageId, 'type', $messageData['type']);
                $this->redis->hSet($messageId, 'attachments', $attachments);
                $this->redis->hSet($messageId, 'forward_message_id', $messageId);

                $avatar = $this->redis->get("user_avatar:{$messageData['user_id']}");
                $forwardData = [
                    'user_id' => $messageData['user_id'],
                    'avatar' => $avatar == false ? "noAvatar.png" : $avatar,
                    'chat_id' => $messageData['chat_id'],
                    'chat_name' => $this->redis->get("user:name:{$messageData['user_id']}")
                ];

                $responseData[] =[
                    'text' => $messageData['text'],
                    'user_id' => $messageData['user_id'],
                    'status' => MessageController::NO_WRITE,
                    'time' => time(),
                    'type' => $messageData['type'],
                    'attachments' => $attachments,
                    'forward_message_id' => $messageId,
                    'forward_data' => json_encode($forwardData)
                ];

                // добавляем сообщение в общий список сообщений

                $this->redis->zAdd('all:messages', ['NX'], self::NO_WRITE, "message:$userId:$messageId");

                MessageHelper::addMessageInChat($this->redis, $forwardChatId, $messageId);

            }

        }
        dump($responseData);
        $notifyUsers = ChatHelper::getChatMembers($forwardChatId,$this->redis);
        return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers,$responseData);

    }


}