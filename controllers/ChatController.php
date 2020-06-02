<?php

namespace Controllers;

use Helpers\ChatHelper;
use Helpers\MessageHelper;
use Helpers\ResponseFormatHelper;
use Illuminate\Database\Capsule\Manager as DB;
use Redis;

class ChatController
{
    private $redis;


    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);

    }


    const PRIVATE = 0;
    const GROUP = 1;
    const CHANNEL = 2;

    const OWNER = 0;
    const SUBSCRIBER = 1;

    const AVAILABLE_COUNT_MESSAGES_IN_REDIS = 40;


    public function create(array $data)
    {
        $userIds = explode(',', $data['user_ids']);

        array_push($userIds, $data['user_id']);


        $anotherUserName = null;
        // временный костыль

        if ($data['type'] == self::PRIVATE) {
            $anotherUser = $userIds[0];
            $checkChat = $this->redis->get("private:{$anotherUser}:{$data['user_id']}");
            $anotherUserAvatar = $this->redis->zRangeByScore('users:avatars', $anotherUser, $anotherUser)[0] ?? '';
            $anotherUserName = $this->redis->zRangeByScore('users:names', $anotherUser, $anotherUser)[0] ?? '';

            if ($checkChat) {
                return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], [
                    'status' => 'false',
                    'chat_id' => $checkChat,
                    'name' => $anotherUserName,
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
                    $this->redis->zAdd("chat:members:{$chatId}", ['NX'], $role, $userId);
                    $this->redis->zAdd("user:chats:{$userId}", ['NX'], time(), $chatId);
                    $this->redis->set("private:{$userId}:{$data['user_id']}", $chatId);
                    $this->redis->set("private:{$data['user_id']}:{$userId}", $chatId);
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
                    $this->redis->zAdd("chat:members:{$chatId}", ['NX'], $role, $userId);
                    $this->redis->zAdd("user:chats:{$userId}", ['NX'], time(), $chatId);
                }
                DB::table('chat_members')->insert($membersData);
        }

        $data = [
            'status' => 'true',
            'chat_name' => $data['type'] == self::PRIVATE ? $anotherUserName : $data['chat_name'],
            'members_count' => count($userIds),
            'chat_id' => $chatId,

        ];

        return ResponseFormatHelper::successResponseInCorrectFormat($userIds, $data);


    }

    public function getAll(array $data)
    {

        $page = $data['page'] ?? 1;
        $onePageChatCount = 20;


        $startChat = $onePageChatCount * $page - $onePageChatCount;
        $endChat = $startChat + $onePageChatCount;

        $userChatIds = $this->redis->zRevRangeByScore("user:chats:{$data['user_id']}", '+inf', '-inf', ['limit' => [$startChat, $endChat]]);


        $userChats = DB::table('chats')->whereIn('id', $userChatIds)->get();

        // нужно для сортировки,
        $responseData = [];

        // получить user_id всех авторов последних сообщений и взять их аватарки и имена из базы

        $allUserIds = [];
        $chatNumber = 0;

        foreach ($userChatIds as $userChatId) {

            $lastMessageId = $this->redis->zRangeByScore("chat:$userChatId", 2, time());
            var_dump($lastMessageId);
            var_dump("test last mess");
            if ($lastMessageId) {

                $lastMessageUserId = $this->redis->hGet($lastMessageId[0], 'user_id');
                $allUserIds[] = $lastMessageUserId;

            }

        }
        $allUserIds = array_unique($allUserIds);
        $users = DB::table('customers')->whereIn('id', $allUserIds)->select('id', 'avatar', 'name')->get();


        foreach ($userChatIds as $userChatId) {
            $chat = $userChats->where('id', $userChatId)->first();
            $lastMessageId = $this->redis->zRange("chat:$userChatId", -1, -1);
            var_dump('all;;');
            var_dump($this->redis->zRange("chat:$userChatId", 0, -1));




            $lastMessage = null;
            $lastMessageUserId = null;

//            var_dump("test " . $lastMessageId);
            var_dump($lastMessageId);

            if ($lastMessageId) {

                $lastMessageDataCrutch = explode(':',array_values($lastMessageId)[0]);

                var_dump($lastMessageDataCrutch);
                if (count($lastMessageDataCrutch) >3) {
                    $lastMessageId[0] = $lastMessageDataCrutch[0].':'.$lastMessageDataCrutch[1].':'.$lastMessageDataCrutch[4];
                    var_dump('crutch');
                    var_dump( $lastMessageId[0]);
                }


                $lastMessage = $this->redis->hGetAll($lastMessageId[0]);
                $lastMessageUserId = $lastMessage['user_id'];
                var_dump($lastMessageUserId);
            }

            if ($chat) {
                $messageOwner = $lastMessage ? $users->where('id', $lastMessage['user_id'] ?? null)->first() : '';
                //@TODO отрефакторить это
                $chatUsers = $this->redis->zRange("chat:members:$userChatId", 0, -1);

                $anotherUsers = array_diff($chatUsers, [$data['user_id']]);
                $anotherUserId = array_shift($anotherUsers);
                var_dump($lastMessageUserId !=$data['user_id']);
                $responseData[] = [
                    'id' => $userChatId,
                    'avatar' => $this->redis->get("user:avatar:{$anotherUserId}"),
                    'name' => $this->redis->get("user:name:{$anotherUserId}"),
                    'type' => $chat->type,
                    'members_count' => $chat->members_count,
                    'unread_messages' => $lastMessageUserId !=$data['user_id'] ? intval($this->redis->get("chat:unwrite:count:{$userChatId}")) :0,
                    'phone' => strval($this->redis->get("user:phone:{$anotherUserId}")),
                    'last_message' => $lastMessage == null ? new \stdClass() : [
                        'id' => array_values($lastMessageId)[0] ?? '',
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

        $page = $data['page'] ?? 1;
        $chatId = $data['chat_id'];
        $count = 20;
        $responseData = [];

        $notifyUsers = $this->redis->zRange("chat:members:{$chatId}", 0, -1);
        array_diff($notifyUsers, [$data['user_id']]);

//        $notifyData = [];
//        $notifyData['owner_id'] = $data['user_id'];
//        $keyForNotifyData = 1;


        if ($page <= 2) {
            $startChat = $count * $page - $count;
            $endChat = $startChat + $count;

//            $chatMessagesId = $this->redis->zRevRangeByScore("chat:{$chatId}", 2, '-inf', ['limit' => [$startChat, $endChat, 'withscores' => true]]);
            $chatMessagesId = array_reverse($this->redis->zRangeByScore("chat:{$chatId}",2,time()+10));

            $allMessages = [];
            $messagesForDivider = [];

            foreach ($chatMessagesId as $chatMessageId) {

                // делаю сообщения прочитанными
                $messageOwner = $this->redis->hGet($chatMessageId, 'user_id');
                $messageWriteStatus = $this->redis->hGet($chatMessageId, 'status');




                if ($messageOwner != $data['user_id'] && $messageWriteStatus != MessageController::WRITE && $messageOwner) {
                    $this->redis->incrBy("chat:unwrite:count:{$chatId}", -1);
                    var_dump("sad test write");
                    var_dump($chatMessageId);
                    $this->redis->hSet($chatMessageId, 'status', MessageController::WRITE);

//                    foreach ($notifyUsers as $notifyUser) {
//                        $notifyData[++$keyForNotifyData] = [
//                            'user_id' => $notifyUser,
//                            'data' => [
//                                'cmd' => 'message:write',
//                                'data' => [
//                                    'chat_id' => $chatId,
//                                    'message_id' => $chatMessageId,
//                                    'owner_id' => $messageOwner,
//                                    'write' => strval(MessageController::WRITE),
//                                ],
//                            ]
//                        ];
//                    }

                }

                $message = $this->redis->hGetAll($chatMessageId);
                if ($message){
                    $allMessages[$chatMessageId] = [
                        'id' => $chatMessageId,
                        'user_id' => $message['user_id'],
                        'avatar' => $this->redis->get("user:avatar:{$message['user_id']}"),
                        'avatar_url' => 'https://indigo24.xyz/uploads/avatars/',
                        'user_name' => $this->redis->get("user:name:{$message['user_id']}"),
                        'text' => $message['text'],
                        'chat_id' => $message['chat_id'],
                        'time' => $message['time'],
                        'write' => $message['status']
                    ];

                    $messagesForDivider[] = [
                        'id' => $chatMessageId,
                        'user_id' => $message['user_id'],
                        'avatar' => $this->redis->get("user:avatar:{$message['user_id']}"),
                        'user_name' => $this->redis->get("user:name:{$message['user_id']}"),
                        'text' => $message['text'],
                        'chat_id' => $message['chat_id'],
                        'time' => $message['time'],
                        'day' => date('d-m-Y', $message['time']),
                        'hour' => date('H:i', $message['time']),
                    ];
                }



            }
            $responseData = MessageHelper::getMessageIncorrectFormat($allMessages, $this->redis);
            $allDays = collect($messagesForDivider)->pluck('day')->unique()->toArray();

            $messagesWithDivider = [];
            foreach ($allDays as $day) {
                $messagesWithDivider[$day] = collect($messagesForDivider)->where('day', $day)->toArray();
            }

            $responseDataWithDivider = [];

            foreach ($messagesWithDivider as $date => $dividerData) {

                $dividerData = array_combine(range(1, count($dividerData)), $dividerData);
                $dividerData[0] = [
                    'text' => $date,
                    'type' => 'divider',
                ];
                sort($dividerData);
                $responseDataWithDivider[] = $dividerData;
            }

            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseData);

        }

        $allMessages = DB::table('messages')
            ->orderBy('time', 'asc')
            ->skip(($page * $count) - $count)
            ->take($count)
            ->get()
            ->toArray();
        $allMessages = array_map(function ($value) {
            return (array)$value;
        }, $allMessages);


        $responseData = MessageHelper::getMessageIncorrectFormat($allMessages, $this->redis);

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseData);
    }

    public function pinned(array $data)
    {
        $chatId = $data['chat_id'];
        $userId = $data['user_id'];

        // проверяем количество уже закрепленных чатов
        $pinnedCount = $this->redis->get("user:pinned:count:{$userId}");

        if ($pinnedCount >= 5) {
            return ResponseFormatHelper::successResponseInCorrectFormat([$userId], [
                'status' => 'false',
                'user_id' => $userId,
                'message' => 'закреплять можно не больше ' . $pinnedCount . ' чатов'
            ]);
        }


        // ставит 1 если юзер закрепил чат если чат не закреплен просто удаляется ключ из redis
        $this->redis->set("chat:pinned:{$userId}:$chatId", 1);
        $this->redis->incrBy("user:pinned:count:{$userId}");


        return ResponseFormatHelper::successResponseInCorrectFormat([$userId], [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'message' => 'чат успешно закреплен',
            'status' => 'true',
            'pinned_count' => $pinnedCount
        ]);
    }


}