<?php

namespace Controllers;

use Redis;

class MessageController
{
    private $r;

    const NO_DELTED_STATUS = 0;
    const DELETED_STATUS = 1;
    const EDITED_STATUS = 2;


    public function __construct()
    {
        $this->r = new Redis();
        $this->r->connect('127.0.0.1', 6379);

    }


    public function store(string $data)
    {

        $r = new Redis();
        $r->connect('127.0.0.1', 6379);

        $data = json_decode($data, true);

        $chatId = $data['chat_id'];
        $messageText = $data['text'];
        $messageFiles = $data['files'];
        $messageImages = $data['images'];
        $unic = $data['unic'];

        $userId = 100;

// проверяем есть ли пользователь в списке подписчиков
        $chatMembers = $r->zRange("chat:members:" . $chatId, 0, -1);

        $checkTrue = array_flip($chatMembers)[$userId] ?? null;
        if (!is_null($checkTrue)) {
            // add message
            $chatMessageCounter = $r->incrBy("chat:message:counter:{$chatId}", 1);
            // как будет происходить создание сообщения

            // увеличивается count всех сообщений
            $messageCounter = $r->incrBy('message:counter', 1);
            // создается само сообщения has table
            $messageHashTableKey = "message:{$messageCounter}";

            $r->hSet($messageHashTableKey, 'chat_id', $chatId);
            $r->hSet($messageHashTableKey, 'user_id', $userId);
            $r->hSet($messageHashTableKey, 'text', $messageText);
            $r->hSet($messageHashTableKey, 'files', $messageFiles);
            $r->hSet($messageHashTableKey, 'created', date('Y-m-d-H:i:s'));
            $r->hSet($messageHashTableKey, 'images', $messageImages);
            $r->hSet($messageHashTableKey, 'edited', 0);
            $r->hSet($messageHashTableKey, 'edited_date', null);

            // сообщение добавляется в чат

            // Увеличиваю counter сообщения каждого чата

            $chatMessageCounter = $r->incrBy('chat:message:counter', 1);

            //Добавляю сообщение в чат

            $r->zAdd("chat:messages:{$chatId}", ['NX'], $chatMessageCounter, $messageCounter);

        }

    }


    public function destroy($data)
    {
        // нужна структура по типу user:chat:message

        $chatId = $data['chat_id'];
        $messageId = $data['message_id'];
        $unic = $data['unic'];

        $userId = $unic == 'testUserUnic' ? 100 : null;

        if ($userId) {
            $this->r->zAdd("user:chat:deleted:message:{$userId}:{$chatId}", ['NX'], time(), $messageId);
        }

        return json_encode([
            'message_id' => $messageId,
            'success' => true,
        ]);
    }


    public function destroyForAll($data)
    {
        $chatId = $data['chat_id'];
        $messageId = $data['message_id'];
        $unic = $data['unic'];
        $userId = $unic == 'testUserUnic' ? 100 : null;

        // достаем это сообщение из списка
        $checkExistMessageId = $this->r->zRangeByLex("chat:messages:{$chatId}", '-', "[$messageId");
        if ($checkExistMessageId) {
            $messageId = $checkExistMessageId[0];
            // достаем само сообщение
            $message = $this->r->hGetAll("message:{$messageId}");
            if ($message) {
                if ($message['user_id'] == $userId) {
                    // удаляем само сообщение
                    $this->r->del("message:{$messageId}");
                }
                if ($this->r->zRem("chat:messages:{$chatId}", $messageId)) {
                    return json_encode([
                        'message_id' => $messageId,
                        'success' => true
                    ]);
                }

            }

        }

    }


    public function create(array $data)
    {
        /*
         *  как мы будем сохранять сообщение? Будет ли у него какой нибудь counter?
         *  если да то что мне возвращать фронту чтобы он мог его удалить или редактировать
         *  когда он его удалил нужно чтобы было понятно удалил он только у себя или сразу у всех в чате
         *  кто по привелегиям может удалять сообщения в чате, есть ли какой-то временной промежуток удаления
         */

        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);

        // у каждого юзера есть counter сообщений
        $userId = $data['user_id'];
        $chatId = $data['chat_id'];

        $messageId = $redis->incrBy("user:message:{$userId}", 1);

        // добаляем сообщение в redis
        $redis->hSet("message:$userId:$messageId", 'user_id', $data['user_id']);
        $redis->hSet("message:$userId:$messageId", 'text', $data['text']);
        $redis->hSet("message:$userId:$messageId", 'chat_id', $data['chat_id']);
        $redis->hSet("message:$userId:$messageId", 'time', time());
        // добавляем сообщение в чат

        $redis->zAdd("chat:{$data['chat_id']}", ['NX'], time(),"message:$userId:$messageId");
        // добавляем сообщение в общий список сообщений
        $redis->zAdd('all:messages', ['NX'], "message:$userId:$messageId", self::NO_DELTED_STATUS);
        // ставим последнее время для фильтрации чатов в списке пользователя
//        $redis->zRem("user:chats:{$userId}",$chatId);

        $notifyUsers = $redis->zRangeByScore("chat:members:{$data['chat_id']}",0,3);

        foreach ($notifyUsers as $notifyUser) {
            $redis->zAdd("user:chats:{$notifyUser}", ['XX'],time(), $chatId);
        }


        return [
            'data' => [
                'status' => 'true',
                'text' => $data['text'],
            ],
            // удаляю из оповещения по сокету самого пользователя который отправил сообщение
            'notify_users' => array_diff($notifyUsers,[$userId]),
        ];

    }


}