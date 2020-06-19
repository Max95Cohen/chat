<?php

namespace Controllers;

use Carbon\Carbon;
use Helpers\ChatHelper;
use Helpers\MessageHelper;
use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Illuminate\Database\Capsule\Manager as DB;
use Patterns\ChatFactory\Factory;
use Patterns\MessageStrategy\Classes\MysqlStrategy;
use Patterns\MessageStrategy\Classes\RedisStrategy;
use Patterns\MessageStrategy\Strategy;
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

        foreach ($userChatIds as $userChatId) {
            $chat = $userChats->where('id', $userChatId)->first();
            $lastMessageId = $this->redis->zRange("chat:$userChatId", -1, -1);

            $lastMessage = $this->redis->hGetAll($lastMessageId[0]);
            $lastMessageUserId = $lastMessage['user_id'];

            if ($chat) {
                $chatStartTime = $this->redis->zRange("chat:{$userChatId}", 0, 0, true);
                $chatStartTime = (int)array_shift($chatStartTime);

                $type = $lastMessage['type'] ?? MessageHelper::TEXT_MESSAGE_TYPE;
                $messageForType = MessageHelper::getAttachmentTypeString($type) ?? null;
                $lastMessageOwnerAvatar = $this->redis->get("user:avatar:{$lastMessageUserId}");

                $responseData[] = [
                    'id' => $userChatId,
                    'avatar' => ChatHelper::getChatAvatar($chat->type, $chat->id, $data['user_id'], $this->redis),
                    'name' => ChatHelper::getChatName($chat->type, $chat->id, $data['user_id'], $this->redis),
                    'type' => $chat->type,
                    'members_count' => $chat->members_count,
                    'unread_messages' => $lastMessageUserId != $data['user_id'] ? intval($this->redis->get("chat:unwrite:count:{$userChatId}")) : 0,
                    'avatar_url' => MessageHelper::AVATAR_URL,
                    'last_message' => [
                        'id' => array_values($lastMessageId)[0] ?? '',
                        'avatar' => $lastMessageOwnerAvatar == false ? UserHelper::DEFAULT_AVATAR : $lastMessageOwnerAvatar,
                        'user_name' => $this->redis->get("user:name:{$lastMessageUserId}") ?? '',
                        'text' => $lastMessage['text'] ?? '',
                        'time' => $lastMessage['time'] == "" ? $chatStartTime : $lastMessage['time'],
                        'type' => $type,
                        'message_for_type' => $messageForType,
                    ],
                ];

            }

        }
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseData);

    }

    public function getOne(array $data)
    {

        $data['page'] = $data['page'] ?? 1;
        $data['count'] = $data['count'] ?? 20;
        $chatId = $data['chat_id'];

        $notifyUsers = $this->redis->zRange("chat:members:{$chatId}", 0, -1);
        array_diff($notifyUsers, [$data['user_id']]);

        $strategy = new Strategy();

        $data['page'] <= 2 ? $strategy->setStrategy(new RedisStrategy()) : $strategy->setStrategy(new MysqlStrategy());

        $responseData = $strategy->executeStrategy("getMessages", $data);
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