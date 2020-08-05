<?php


namespace Helpers;


use Controllers\ChatController;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Capsule\Manager as DB;
use Redis;

class ChatHelper
{
    /**
     * @param int $chatId
     * @param Redis $redis
     * @return array
     */
    public static function getChatMembers(int $chatId, Redis $redis): array
    {
        return $redis->zRangeByScore("chat:members:{$chatId}", ChatController::SUBSCRIBER, '+inf');
    }

    /**
     * @param array $data
     * @param int $memberCount
     * @return int
     */
    public static function createChat(array $data, int $memberCount): int
    {
        return DB::table('chats')->insertGetId([
            'owner_id' => $data['user_id'],
            'name' => $data['chat_name'],
            'type' => $data['type'],
            'members_count' => $memberCount,
        ]);
    }

    /**
     * @param int $type
     * @param int $chatId
     * @param int $userId
     * @param Redis $redis
     * @return string
     */
    public static function getChatName(int $type, int $chatId, int $userId, Redis $redis): string
    {
        if ($type == ChatController::PRIVATE) {
            $chatUsers = $redis->zRange("chat:members:$chatId", 0, -1);

            $anotherUsers = array_diff($chatUsers, [$userId]);
            $anotherUserId = array_shift($anotherUsers);
            $chatName = $redis->get("user:name:{$anotherUserId}");

            return $chatName == false ? '' : $chatName;
        }

        $chatNameInRedis = $redis->get("chat:name:{$chatId}");

        return $chatNameInRedis == false ? Manager::table('chats')->where('id', $chatId)->value('name') : $chatNameInRedis;
    }

    /**
     * @param int $type
     * @param int $chatId
     * @param int $userId
     * @param Redis $redis
     * @return string
     */
    public static function getChatAvatar(int $type, int $chatId, int $userId, Redis $redis): string
    {
        if ($type == ChatController::PRIVATE) {
            $chatUsers = $redis->zRange("chat:members:$chatId", 0, -1);

            $anotherUsers = array_diff($chatUsers, [$userId]);
            $anotherUserId = array_shift($anotherUsers);

            return $redis->get("user:avatar:{$anotherUserId}") ?? UserHelper::DEFAULT_AVATAR;
        }

        $chatAvatarInRedis = $redis->get("chat:avatar:{$chatId}");
        $chatAvatarInRedis = $redis->get("chat:avatar:{$chatId}") == false ? null : $chatAvatarInRedis;
        $chatAvatarInMysql = Manager::table('chats')->where('id', $chatId)->value('avatar') ?? UserHelper::DEFAULT_AVATAR;

        return $chatAvatarInRedis ?? $chatAvatarInMysql;

    }

    /**
     * @param int $chatId
     * @param Redis $redis
     * @param array $chatMembers
     */
    public static function incrUnWriteCountForMembers(int $chatId, Redis $redis, array $chatMembers): void
    {
        foreach ($chatMembers as $chatMember) {
            $redis->incr("usr:unw:{$chatMember}:{$chatId}", 1);
        }
    }

    /**
     * @param int $chatId
     * @param int $userId
     * @param Redis $redis
     */
    public static function nullifyUnWriteCount(int $chatId, int $userId, Redis $redis) :void
    {
        $redis->set("usr:unw:{$userId}:{$chatId}",0);
    }

    /**
     * @param int $userId
     * @param int $chatId
     * @param Redis $redis
     * @return array
     */
    public static function checkChatMute(int $userId, int $chatId, Redis $redis) :int
    {
        $mute =  $redis->zRangeByScore("u:mute:ch:{$userId}",$chatId,$chatId);
        return $mute == [] ? ChatController::CHAT_UNMUTE : ChatController::CHAT_MUTE;
    }


}