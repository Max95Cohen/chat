<?php


namespace Helpers;


use Controllers\ChatController;
use Illuminate\Database\Capsule\Manager as DB;
use Redis;

class ChatHelper
{
    /**
     * @param int $chatId
     * @param Redis $redis
     * @return array
     */
    public static function getChatMembers(int $chatId, Redis $redis) :array
    {
        return $redis->zRangeByScore("chat:members:{$chatId}",ChatController::OWNER,'+inf');
    }

    /**
     * @param array $data
     * @param int $memberCount
     * @return int
     */
    public static function createChat(array $data, int $memberCount) :int
    {
        return DB::table('chats')->insertGetId([
            'owner_id' => $data['user_id'],
            'name' => $data['chat_name'],
            'type' => $data['type'],
            'members_count' => $memberCount,
        ]);
    }

    public static function getAvatar(int $type, int $chatId) :string
    {

    }

}