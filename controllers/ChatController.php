<?php

namespace Controllers;

use Helpers\ChatHelper;
use Helpers\ResponseFormatHelper;
use Illuminate\Database\Capsule\Manager as DB;
use Redis;

class ChatController
{
    private $r;

    public function __construct()
    {
        $this->r = new Redis();
        $this->r->connect('127.0.0.1', 6379);
    }


    const PRIVATE = 0;
    const GROUP = 1;
    const CHANNEL = 2;

    const OWNER = 0;
    const SUBSCRIBER = 1;


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


    public function create(array $data)
    {
        $userIds = explode(',', $data['user_ids']);

        array_push($userIds, $data['user_id']);
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);


        $chatId = DB::table('chats')->insertGetId([
            'owner_id' => $data['user_id'],
            'name' => $data['chat_name'],
            'type' => $data['type'],
            'members_count' => count($userIds),
        ]);
        $membersData = [];

        switch ($data['type']) {
            case self::PRIVATE :
                foreach ($userIds as $userId) {
                    $role = $userId == $data['user_id'] ? self::OWNER : self::SUBSCRIBER;
                    $membersData[] = [
                        'user_id' => $userId,
                        'chat_id' => $chatId,
                        'role' => $role,
                    ];
                    $redis->zAdd("chat:members:{$chatId}", ['NX'], $role, $userId);
                    $redis->zAdd("user:chats:{$userId}", ['NX'], time(), $chatId);
                }
                DB::table('chat_members')->insert($membersData);
        }

        $data = [
            'status' => true,
            'chat_id' => $chatId,
        ];

        return ResponseFormatHelper::successResponseInCorrectFormat($userIds, $data);


    }

    public function getAll(array $data)
    {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);

        $page = $data['page'] ?? 1;
        $onePageChatCount = 20;


        $startChat = $onePageChatCount * $page - $onePageChatCount;
        $endChat = $startChat + $onePageChatCount;

        $userChatIds = $redis->zRevRangeByScore("user:chats:{$data['user_id']}", '+inf', '-inf', ['limit' => [$startChat, $endChat]]);


        $userChats = DB::table('chats')->whereIn('id', $userChatIds)->get();

        // нужно для сортировки,
        $responseData = [];

        // получить user_id всех авторов последних сообщений и взять их аватарки и имена из базы

        $allUserIds = [];
        foreach ($userChatIds as $userChatId) {
            $lastMessageId = $redis->zRange("chat:$userChatId", -1, -1);

            if ($lastMessageId) {
                $lastMessageUserId = $redis->hGet("message:{$lastMessageId[0]}", 'user_id');
                $allUserIds[] = $lastMessageUserId[0];

            }

        }
        $allUserIds = array_unique($allUserIds);
        $users = DB::table('customers')->whereIn('id', $allUserIds)->select('id', 'avatar', 'name')->get();


        foreach ($userChatIds as $userChatId) {
            $chat = $userChats->where('id', $userChatId)->first();
            $lastMessageId = $redis->zRange("chat:$userChatId", -1, -1);
            $lastMessage = null;

            if ($lastMessageId) {
                $lastMessage = $redis->hGetAll("message:{$lastMessageId[0]}");
            }

            if ($chat && $lastMessage) {
                $messageOwner = $users->where('id', $lastMessage['user_id'])->first();
                $responseData[] = [
                    'id' => $userChatId,
                    'avatar' => 'todo avatar',
                    'name' => $chat->name,
                    'type' => $chat->type,
                    'members_count' => $chat->members_count,
                    'last_message' => $lastMessage != [] ? [
                        'id' => $lastMessageId,
                        'avatar' => $messageOwner->avatar ?? '',
                        'user_name' => $messageOwner->user_name ?? '',
                        'text' => $lastMessage['text'] ?? '',
                        'time' => $lastMessage['time'] ?? '',
                    ] : [],
                ];
            }

        }
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseData);

    }

    public function getOne(array $data)
    {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);

        $page = $data['page'] ?? 1;
        $chatId = $data['chat_id'];
        $onePageMessageCount = 20;
        $responseData = [];
        if ($page < 2) {
            $startChat = $onePageMessageCount * $page - $onePageMessageCount;
            $endChat = $startChat + $onePageMessageCount;

            $chatMessagesId = $redis->zRevRangeByScore("chat:{$chatId}", '+inf', '-inf', ['limit' => [$startChat, $endChat, 'withscores' => true]]);

            $allMessages = [];
            foreach ($chatMessagesId as $chatMessageId) {
                $message = $redis->hGetAll($chatMessageId);
                $allMessages[$chatMessageId] = [
                    'user_id' => $message['user_id'],
                    'text' => $message['text'],
                    'chat_id' => $message['chat_id'],
                    'time' => $message['time'],
                ];
            }
            $needleUsersIds = collect($allMessages)->pluck('user_id')->unique();
            $users = DB::table('customers')->whereIn('id', $needleUsersIds)->get();

            foreach ($allMessages as $messageId => $message) {
                $user = $users->where('id', $message['user_id'])->first();
                $responseData[] = [
                    'user_id' => $message['user_id'],
                    'user_name' => $user->name,
                    'avatar' => $user->avatar,
                    'text' => $message['text'],
                    'time' => $message['time'],
                    'write' => '0'
                ];
            }
            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],$responseData);

        }


    }


}