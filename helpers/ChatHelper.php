<?php


namespace Helpers;


use Controllers\ChatController;
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
}