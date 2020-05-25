<?php

namespace Controllers;

use Redis;

class ChatController
{
    private $r;

    public function __construct()
    {
        $this->r = new Redis();
        $this->r->connect('127.0.0.1', 6379);
    }


    const PRIVATE = 1;
    const ME_TO_ME = 2;
    const GROUP = 3;
    const BUSINESS = 4;

    const ROLE_OWNER = 1;
    const ROLE_MEMBER = 2;


    public function store(string $data)
    {
        // @TODO deleted test data

        $data = json_encode([
            'user_id' => 100,
            'members_ids' => [
                102, 130, 290
            ],
            'type' => self::GROUP,
        ]);
        $r = new Redis();
        $r->connect('127.0.0.1', 6379);

        // создать структуру для чата
        // добавить для юзера чат в его список чатов
        // добавить сам чат в свою структуру

        $data = json_decode($data, true);
        $members = $data['members_ids'];
        $userId = $data['user_id'];
        // делаем инкремент для общего количества чатов
        $chatId = $r->incrBy('chat:id', 1);
        // добавляем чат
        $chatKey = 'chat:' . $chatId;

        $r->hSet($chatKey, 'owner_id', $userId);
        $r->hSet($chatKey, 'type', $data['type']);
        $r->hSet($chatKey, 'main_image', $data['main_image']);

        // добавляем подписчиков для чата
        foreach ($members as $k => $memberId) {
            $r->zAdd('chat:members:' . $chatId, ['NX'], $k, $memberId);
        }
        //  заполняем чаты юзера
        $userChatCounter = $r->incrBy('chat:counter:user:' . $userId, 1);

        $r->zAdd('user:chats:user_id:' . $userId, ['NX'], $userChatCounter, $chatId);

        //возвращаем фронтендеру статус
        return json_encode([
            'id' => $chatId,
            'owner_id' => $userId,
            'status' => 'ok',
            'member_count' => count($members)
        ]);

    }


    public function getUserChats($data)
    {

        if ($data['unic'] == 'testUserUnic') {
            $userId = 100;
        }

        // достаем все чаты

//        $endGetChatMessage = $perPage * $page;
//        $startGetChatMessage = $endGetChatMessage - $perPage;

        // Получаем id все чатов пользователя
        $chatsId = $this->r->zRange("user:chats:user_id:{$userId}", 0, -1);

        $chatsData = [];
        foreach ($chatsId as $k => $chatId) {

            // достать последнее сообщение для чата
            $lastMessageId = $this->r->zRange("chat:messages:{$chatId}", -1, -1)[0] ?? null;
            $lastMessage = $this->r->hGetAll("message:{$lastMessageId}");
            // достать сам чат
            $chat = $this->r->hGetAll("chat:{$chatId}");
            $chatsData[$k]['chat_id'] = $lastMessage['chat_id'];
            $chatsData[$k]['text'] = $lastMessage['text'];
            $chatsData[$k]['main_image'] = $chat['main_image'];
            $chatsData[$k]['type'] = $chat['type'];
            $chatsData[$k]['last_message_time'] = $lastMessage['created'];
        }
        return json_encode($chatsData);
    }

    public function getChatMessage($data)
    {
        if ($data['unic'] == 'testUserUnic') {
            $userId = 100;
        }


        $chatId = $data['chat_id'];

        //pagination
        $perPage = $data['per_page'];
        $page = $data['page'];


        $startMessagePos = $perPage * $page - $perPage;
        $endMessagePos = $perPage * $page;

        $chatMessagesId = $this->r->zRange("chat:messages:{$chatId}", $startMessagePos, $endMessagePos);

        // получаю все удаленные сообщения юзера из этого чата
        $deletedMessages = $this->r->zRange("user:chat:deleted:message:{$userId}:{$chatId}", 0, -1);
        $checkerForDeletedMessages = array_flip($deletedMessages);

        $messagesData = [];
        foreach ($chatMessagesId as $messageId) {
            $checkDeletedMessage = $checkerForDeletedMessages[$messageId] ?? null;
            // проверяю и если сообщение у юзера удалено не возвращаю его фронтендеру
            if (is_null($checkDeletedMessage)) {
                $message = $this->r->hGetAll("message:{$messageId}");
                $messagesData[] = [
                    'id' => $messageId,
                    'user_id' => $message['user_id'],
                    'its_me' => $message['user_id'] == $userId ? true : false,
                    'text' => $message['text'],
                    'files' => $message['files'],
                    'created' => $message['created'],
                    'images' => $message['images']
                ];
            }

        }
        return json_encode($messagesData);
    }


}