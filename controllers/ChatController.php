<?php

namespace Controllers;

use Carbon\Carbon;
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
    const ADMIN = 2;

    const BANNED = -1;

    const CHAT_MEDIA_URL = '';

    public static function getRolesForOwner()
    {
        return [
            self::SUBSCRIBER,
            self::ADMIN,
            self::BANNED
        ];
    }

    public static function getRolesForAdministrators()
    {
        return [
            self::OWNER,
            self::ADMIN,
        ];
    }


    const AVAILABLE_COUNT_MESSAGES_IN_REDIS = 40;


    public function create(array $data)
    {
        $userIds = explode(',', $data['user_ids']);

        array_push($userIds, $data['user_id']);

        $anotherUserName = null;

        if ($data['type'] == self::PRIVATE) {
            $anotherUser = $userIds[0];
            $checkChat = $this->redis->get("private:{$anotherUser}:{$data['user_id']}");
            $anotherUserAvatar = $this->redis->get("user:avatar:{$anotherUser}") ?? '';
            $anotherUserName = $this->redis->get("user:name:{$anotherUser}");

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
                $this->redis->zAdd("chat:{$chatId}", ['NX'], time(), "chat:message:create");
                break;
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
                $this->redis->zAdd("chat:{$chatId}", ['NX'], time(), "group:message:create");
                break;
        }



        $data = [
            'status' => 'true',
            'chat_name' => $data['type'] == self::PRIVATE ? $anotherUserName : $data['chat_name'],
            'members_count' => count($userIds),
            'chat_id' => $chatId,

        ];
        $this->redis->close();
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

        foreach ($userChatIds as $userChatId) {

            $lastMessageId = $this->redis->zRangeByScore("chat:$userChatId", 2, time());
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

            $lastMessage = $this->redis->hGetAll($lastMessageId[0]);
            $lastMessageUserId = $lastMessage['user_id'];

            if ($chat) {
                $messageOwner = $lastMessage ? $users->where('id', $lastMessage['user_id'] ?? null)->first() : '';
                //@TODO отрефакторить это
                $chatUsers = $this->redis->zRange("chat:members:$userChatId", 0, -1);

                $anotherUsers = array_diff($chatUsers, [$data['user_id']]);
                $anotherUserId = array_shift($anotherUsers);
                $crutchUserName = $this->redis->get("user:name:{$anotherUserId}") == false ? '' : $this->redis->get("user:name:{$anotherUserId}");

                // @TODO отрекфакторить костыль

                if ($chat->type == self::PRIVATE) {
                    $checkAvatar = $this->redis->get("user:avatar:{$anotherUserId}");

                    $chatAvatar = $checkAvatar == false ? 'noAvatar.png' : $checkAvatar;
                } else {
                    $chatAvatar = 'noAvatar.png';
                }

                $chatStartTime = $this->redis->zRange("chat:{$userChatId}",0,0,true);
                $chatStartTime =(int)array_shift($chatStartTime);

                $type = $lastMessage['type'] ?? MessageHelper::TEXT_MESSAGE_TYPE;
                #$messageForType = MessageHelper::getAttachmentTypeString($type) ?? null;

                $responseData[] = [
                    'id' => $userChatId,
                    'avatar' => $chatAvatar,
                    'name' => $chat->type == self::PRIVATE ? $crutchUserName : $chat->name,
                    'type' => $chat->type,
                    'members_count' => $chat->members_count,
                    'unread_messages' => $lastMessageUserId != $data['user_id'] ? intval($this->redis->get("chat:unwrite:count:{$userChatId}")) : 0,
                    'phone' => strval($this->redis->get("user:phone:{$anotherUserId}")),
                    'another_user_id' => $anotherUserId ?? 0,
                    'last_message' => [
                        'id' => array_values($lastMessageId)[0] ?? '',
                        'avatar' => $messageOwner->avatar ?? '',
                        'user_name' => $messageOwner->user_name ?? '',
                        'text' => $lastMessage['text'] ?? '',
                        'time' => $lastMessage['time'] == "" ? $chatStartTime : $lastMessage['time'],
                        'type' => $type,
                        #'message_for_type' =>$messageForType,
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
        $divider = $data['divider'] ?? null;
        $count = 20;

        $notifyUsers = $this->redis->zRange("chat:members:{$chatId}", 0, -1);
        array_diff($notifyUsers, [$data['user_id']]);


        if ($page <= 2) {
            $startChat = $count * $page - $count;
            $endChat = $startChat + $count;
            $chatMessagesId = $this->redis->zRevRangeByScore("chat:{$chatId}", '+inf', '-inf', ['limit' => [$startChat, $endChat, 'withscores' => true]]);

            $allMessages = [];
            $messagesForDivider = [];
            foreach ($chatMessagesId as $chatMessageId) {
                $chatStartTime = $this->redis->zRange("chat:{$chatId}",0,0,true);
                $chatStartTime =(int)array_shift($chatStartTime);
                // делаю сообщения прочитанными
                $messageOwner = $this->redis->hGet($chatMessageId, 'user_id');
                $messageWriteStatus = $this->redis->hGet($chatMessageId, 'status');


                if ($messageOwner != $data['user_id'] && $messageWriteStatus != MessageController::WRITE && $messageOwner) {
                    $this->redis->incrBy("chat:unwrite:count:{$chatId}", -1);
                    $this->redis->hMSet($chatMessageId, ['status' => MessageController::WRITE]);

                }

                $message = $this->redis->hGetAll($chatMessageId);
                $attachments = $message['attachments'] ?? null;


                if ($message) {
                    $allMessages[$chatMessageId] = [
                        'id' => $chatMessageId,
                        'user_id' => $message['user_id'],
                        'avatar' => $this->redis->get("user:avatar:{$message['user_id']}"),
                        'avatar_url' => 'https://indigo24.xyz/uploads/avatars/',
                        'user_name' => $this->redis->get("user:name:{$message['user_id']}"),
                        'text' => $message['text'] ?? null,
                        'chat_id' => $message['chat_id'],
                        'time' => $message['time'] == '' ? $chatStartTime : $message['time'],
                        'write' => $message['status'],
                        'attachments' => $attachments,
                        'type' => $message['type'] ?? 0,
                    ];
                    $messagesForDivider[] = [
                        'id' => $chatMessageId,
                        'user_id' => $message['user_id'],
                        'avatar' => $this->redis->get("user:avatar:{$message['user_id']}"),
                        'user_name' => $this->redis->get("user:name:{$message['user_id']}"),
                        'text' => $message['text'] ?? null,
                        'type' => $message['type'] ?? 0,
                        'chat_id' => $message['chat_id'],
                        'time' => $message['time'],
                        'attachments' => $attachments,
                        'day' => date('d-m-Y', $message['time'] ?? 1),
                        'hour' => date('H:i', $message['time'] ?? 1),
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
            if ($divider) {
                $this->redis->close();
                return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseDataWithDivider);
            }

            $this->redis->close();
            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseData);

        }

        $allMessages = DB::table('messages')
            ->orderBy('time', 'desc')
            ->where('chat_id',$chatId)
            ->skip(($page * $count) - $count)
            ->take($count)
            ->get();

        $writeCount = $allMessages->where('user_id','!=',$data['user_id'])->where('status',MessageController::NO_WRITE)->count();;


        if ($allMessages->last()->user_id !== $data['user_id']) {
             DB::table('messages')
                ->orderBy('time', 'desc')
                ->where('chat_id',$chatId)
                ->skip(($page * $count) - $count)
                ->update(['status' => MessageController::WRITE]);
        }



        $messagesForDivider = [];
        $attachments = $message['attachments'] ?? null;
        foreach ($allMessages as $message) {
            // возвращаю сообщения в корректном формате
            $messagesForDivider[] = [
                'id' => strval($message->id),
                'user_id' => $message->user_id,
                'avatar' => $this->redis->get("user:avatar:{$message->user_id}"),
                'user_name' => $this->redis->get("user:name:{$message->user_id}"),
                'text' => $message->text ?? null,
                'chat_id' => $message->chat_id,
                'type' => $message->type ?? 0,
                'time' => $message->time,
                'day' => Carbon::parse($message->time)->format('d-m-Y'),
                'hour' => Carbon::parse($message->time)->format('H:i'),
                'attachments' => $attachments,
            ];
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

        }

        $writeCount = $writeCount !=0 ? -1 * $writeCount : 0;

        $this->redis->incrBy("chat:unwrite:count:{$chatId}", $writeCount);
        if ($divider) {
            $this->redis->close();
            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseDataWithDivider[0]);
        }


        $responseData = MessageHelper::getMessageIncorrectFormat($allMessages, $this->redis);
        $this->redis->close();
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