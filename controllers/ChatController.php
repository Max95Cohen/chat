<?php

namespace Controllers;

use Helpers\ChatHelper;
use Helpers\MessageHelper;
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
        $anotherUserName = null;
        // временный костыль

        if ($data['type'] == self::PRIVATE) {
            $anotherUser = $userIds[0];
            $checkChat =$redis->get("private:{$anotherUser}:{$data['user_id']}");
            $anotherUserAvatar = $redis->zRangeByScore('users:avatars',$anotherUser,$anotherUser)[0] ?? '';
            $anotherUserName = $redis->zRangeByScore('users:names',$anotherUser,$anotherUser)[0] ?? '';

            if ($checkChat) {
                return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],[
                    'status' => 'false',
                    'chat_id' => $checkChat,
                    'name' =>$anotherUserName,
                    'avatar' => $anotherUserAvatar,
                ]);
            }
        }



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
                    $redis->set("private:{$userId}:{$data['user_id']}",$chatId);
                    $redis->set("private:{$data['user_id']}:{$userId}",$chatId);
                }
                DB::table('chat_members')->insert($membersData);
            case self::GROUP:
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
            'status' => 'true',
            'chat_name' => $data['type'] == self::PRIVATE ? $anotherUserName : $data['chat_name'],
            'members_count' =>  count($userIds),
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
                $lastMessage = $redis->hGetAll($lastMessageId[0]);
            }

            if ($chat) {
                $messageOwner =$lastMessage ? $users->where('id', $lastMessage['user_id'] ?? null)->first() : '';
                //@TODO отрефакторить это
                $chatUsers = $redis->zRange("chat:members:$userChatId",0,-1);

                $anotherUsers = array_diff($chatUsers,[$data['user_id']]);
                $anotherUserId = array_shift($anotherUsers);

                $responseData[] = [
                    'id' => $userChatId,
                    'avatar' => $redis->get("user:avatar:{$anotherUserId}"),
                    'name' => $redis->get("user:name:{$anotherUserId}"),
                    'type' => $chat->type,
                    'members_count' => $chat->members_count,
                    'last_message' => $lastMessage ==null ? new \stdClass() : [
                        'id' => $lastMessageId[0] ?? '',
                        'avatar' => $messageOwner->avatar ?? '',
                        'user_name' => $messageOwner->user_name ?? '',
                        'text' => $lastMessage['text'] ?? '',
                        'time' => $lastMessage['time'] ?? '',
                    ],
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
        $count = 20;
        $responseData = [];

        if ($page < 200) {
            $startChat = $count * $page - $count;
            $endChat = $startChat + $count;

            $chatMessagesId = $redis->zRevRangeByScore("chat:{$chatId}", '+inf', '-inf', ['limit' => [$startChat, $endChat, 'withscores' => true]]);



            $allMessages = [];
            $messagesForDivider = [];

            foreach ($chatMessagesId as $chatMessageId) {

                $message = $redis->hGetAll($chatMessageId);

                $allMessages[$chatMessageId] = [
                    'id' =>$chatMessageId,
                    'user_id' => $message['user_id'],
                    'avatar' => $redis->get("user:avatar:{$message['user_id']}"),
                    'avatar_url' =>'https://indigo24.xyz/uploads/avatars/',
                    'user_name' => $redis->get("user:name:{$message['user_id']}"),
                    'text' => $message['text'],
                    'chat_id' => $message['chat_id'],
                    'time' => $message['time'],
                    'write' => $message['status']
                ];

                $messagesForDivider[] = [
                    'id' =>$chatMessageId,
                    'user_id' => $message['user_id'],
                    'avatar' => $redis->get("user:avatar:{$message['user_id']}"),
                    'user_name' => $redis->get("user:name:{$message['user_id']}"),
                    'text' => $message['text'],
                    'chat_id' => $message['chat_id'],
                    'time' => $message['time'],
                    'day' => date('d-m-Y',$message['time']),
                    'hour' => date('H:i',$message['time']),
                ];

                // делаю сообщения прочитанными
                $redis->hSet($chatMessageId, 'status', MessageController::WRITE);

            }
            $responseData = MessageHelper::getMessageIncorrectFormat($allMessages,$redis);
            $allDays = collect($messagesForDivider)->pluck('day')->unique()->toArray();

            $messagesWithDivider = [];
            foreach ($allDays as $day) {
                $messagesWithDivider[$day] = collect($messagesForDivider)->where('day',$day)->toArray();
            }

            $responseDataWithDivider = [];

            foreach ($messagesWithDivider as $date => $dividerData) {

                $dividerData = array_combine(range(1, count($dividerData)), $dividerData);
                $dividerData[0] = [
                    'text' => $date,
                    'type' => 'divider',
                ];
                sort($dividerData);
                $responseDataWithDivider[] =$dividerData;
            }

            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],$responseData);

        }

        $allMessages = DB::table('messages')
            ->orderBy('time','asc')
            ->skip(($page * $count) - $count)
            ->take($count)
            ->get()
            ->toArray();
        $allMessages = array_map(function ($value) {
            return (array)$value;
        }, $allMessages);

        $responseData = MessageHelper::getMessageIncorrectFormat($allMessages,$redis);
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],$responseData);
    }


}