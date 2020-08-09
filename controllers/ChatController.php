<?php

namespace Controllers;

use Carbon\Carbon;
use Helpers\ChatHelper;
use Helpers\MessageHelper;
use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Capsule\Manager as DB;
use Patterns\ChatFactory\Factory;
use Patterns\MessageStrategy\Classes\BannedStrategy;
use Patterns\MessageStrategy\Classes\MysqlStrategy;
use Patterns\MessageStrategy\Classes\RedisStrategy;
use Patterns\MessageStrategy\Strategy;
use Redis;
use Traits\RedisTrait;

class ChatController
{
    use RedisTrait;

    const PRIVATE = 0;
    const GROUP = 1;
    const CHANNEL = 2;

    const OWNER = 100;
    const ADMIN = 50;
    const SUBSCRIBER = 2;

    const BANNED = -1;

    const CHAT_MEDIA_URL = '';

    const CHAT_MUTE = 1;
    const CHAT_UNMUTE = 0;


    //@TODO вынести эти 2 функции в отдельный хелпер
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


    /**
     * @param array $data
     * @return array[]
     */
    public function create(array $data)
    {

        $data = Factory::getItem($data['type'])->create($data, $this->redis);

        $notifyUsers = ChatHelper::getChatMembers($data['chat_id'], $this->redis);
        $this->redis->close();

        return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers, $data);

    }

    /**
     * @param array $data
     * @return array[]
     */
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

        foreach ($userChatIds as $chatId) {
            $chat = $userChats->where('id', $chatId)->first();
            $lastMessageId = $this->redis->zRange("chat:$chatId", -1, -1);
            //@TODO тестовая фигня нужно проверить и исправить
            $lastMessageId = $lastMessageId[0] ?? null;
            $lastMessage = $lastMessageId ? $this->redis->hGetAll($lastMessageId) : [];
            $lastMessageUserId = $lastMessage['user_id'] ?? null;


            if ($chat) {
                $chatStartTime = $this->redis->zRange("chat:{$chatId}", 0, 0, true);
                $chatStartTime = $chatStartTime == false ? "" : (int)array_shift($chatStartTime);


                $type = $lastMessage['type'] ?? MessageHelper::TEXT_MESSAGE_TYPE;
                $messageForType = MessageHelper::getAttachmentTypeString($type) ?? null;
                $lastMessageOwnerAvatar = $this->redis->get("user:avatar:{$lastMessageUserId}");

                $lastMessageText = $lastMessage['text'] ?? null;
                $lastMessageText = $type == MessageHelper::MONEY_MESSAGE_TYPE ? '' : $lastMessageText;
                $lastMessageTime = $lastMessage['time'] ?? '';

                //@TODO пока нужно потом отрефакторить
                $chatUsers = $this->redis->zRangeByScore("chat:members:$chatId", 0, self::OWNER);

                $anotherUsers = array_diff($chatUsers, [$data['user_id']]);
                $anotherUserId = array_shift($anotherUsers);
                //*****
                // @TODO пока тут просто тупой if потом отрефакторю
                $checkNotBanned = in_array($data['user_id'], $chatUsers);

                $lastMessageTime = $lastMessageTime == "" ? $chatStartTime : $lastMessageTime;


                $lastMessageData = [
                    'message_id' => $lastMessageId ?? '',
                    'avatar' => $lastMessageOwnerAvatar == false ? UserHelper::DEFAULT_AVATAR : $lastMessageOwnerAvatar,
                    'user_name' => $this->redis->get("user:name:{$lastMessageUserId}") ?? '',
                    'text' => $lastMessageText ?? '',
                    'time' => $lastMessageTime,
                    'type' => $type,
                    'message_for_type' => $messageForType,
                ];

                if (!$checkNotBanned) {
                    $bannedTime = $this->redis->get("user:delete:in:chat:{$data['user_id']}:{$chatId}");
                    $lastMessageData = [
                        'text' => 'Вас исключили из группы',
                        'type' => MessageHelper::SYSTEM_MESSAGE_TYPE,
                        'message_for_type' => 'Вас исключили из группы',
                        'time' => $bannedTime == false ? null : $bannedTime,
                    ];
                }

                $responseData[] = [
                    'id' => $chatId,
                    'avatar' => ChatHelper::getChatAvatar($chat->type, $chat->id, $data['user_id'], $this->redis),
                    'name' => ChatHelper::getChatName($chat->type, $chat->id, $data['user_id'], $this->redis),
                    'type' => $chat->type,
                    'members_count' => $chat->members_count,
                    'unread_messages' => $lastMessageUserId != $data['user_id'] ? intval($this->redis->get("usr:unw:{$data['user_id']}:{$chatId}")) : 0,
                    'avatar_url' => MessageHelper::AVATAR_URL,
                    'another_user_id' => $anotherUserId,
                    'another_user_phone' => $this->redis->get("user:phone:{$anotherUserId}"),
                    'last_message' => $lastMessageData,
                    'time' => $bannedTime ?? $lastMessageTime,
                    'members' => $chatMembersData ?? null,
                    'mute' => ChatHelper::checkChatMute($data['user_id'],$chatId,$this->redis)
                ];


            }

        }
        $this->redis->close();
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseData);

    }

    public function getOne(array $data)
    {

        $data['page'] = $data['page'] ?? 1;
        $data['count'] = $data['count'] ?? 20;
        $chatId = $data['chat_id'];

        $chatMembers = $this->redis->zRangeByScore("chat:members:{$chatId}", 0, '+inf', ['withscores' => true]);
        $strategy = new Strategy();

        $checkBanned = $chatMembers[$data['user_id']] ?? null;

        $data['page'] <= 2 ? $strategy->setStrategy(new RedisStrategy()) : $strategy->setStrategy(new MysqlStrategy());
        if ($checkBanned == self::BANNED) {
            $strategy->setStrategy(new BannedStrategy());
        }

        $responseData = $strategy->executeStrategy("getMessages", $data);

        ChatHelper::nullifyUnWriteCount($chatId,$data['user_id'],$this->redis);

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

    /**
     * @param array $data
     * @return array
     */
    public function changeChatName(array $data): array
    {
        $userId = $data['user_id'];
        $chatId = $data['chat_id'];
        $chatName = $data['chat_name'];

        $chatMembers = $this->redis->zRangeByScore("chat:members:{$chatId}", ChatController::OWNER, ChatController::OWNER);
        $chatType = Manager::table('chats')->where('id', $chatId)->value('type');

        if (in_array($userId, $chatMembers) && $chatType == ChatController::GROUP) {
            Manager::table('chats')->where('id', $chatId)->update([
                'name' => $chatName,
            ]);
            $notifyUsers = ChatHelper::getChatMembers($chatId, $this->redis);
            return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers, [
                'status' => 'true',
                'chat_name' => $chatName,
                'chat_id' => $chatId
            ]);
        }
        return ResponseFormatHelper::successResponseInCorrectFormat([$userId], [
            'status' => 'false',
            'message' => 'только в группе можно менять имя и только владелец может'
        ]);

    }

    /**
     * @param array $data
     * @return array
     */
    public function muteChat(array $data) :array
    {
        $userId = $data['user_id'];
        $chatId = $data['chat_id'];
        $mute = $data['mute'] ?? self::CHAT_MUTE;

        if ($mute == self::CHAT_MUTE) {
            $this->redis->zAdd("u:mute:ch:{$userId}",['NX'],$chatId,$chatId);

            $this->redis->close();

            return ResponseFormatHelper::successResponseInCorrectFormat([$userId],[
                'chat_id' => $chatId,
                'mute' => self::CHAT_MUTE,
            ]);

        }

        $this->redis->zRem("u:mute:ch:{$userId}",$chatId);

        return ResponseFormatHelper::successResponseInCorrectFormat([$userId],[
            'chat_id' => $chatId,
            'mute' => self::CHAT_UNMUTE,
        ]);



    }




}