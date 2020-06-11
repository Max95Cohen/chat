<?php


namespace Helpers;

use Controllers\ChatController;
use Redis;

class MessageHelper
{

    const TEXT_MESSAGE_TYPE = 0;
    const IMAGE_MESSAGE_TYPE = 1;
    const DOCUMENT_MESSAGE_TYPE = 2;
    const VOICE_MESSAGE_TYPE = 3;

    const MESSAGE_NO_WRITE_STATUS = 0;
    const MESSAGE_WRITE_STATUS = 1;

    public static function getMessageIncorrectFormat($allMessages, Redis $redis)
    {
        $responseData = [];
        foreach ($allMessages as $messageId => $message) {
            $avatar = $redis->get("user:avatar:{$message['user_id']}");
            $name = $redis->get("user:name:{$message['user_id']}");
            $responseData[] = [
                'id' => $message['id'],
                'user_id' => $message['user_id'],
                'user_name' => $name,
                'avatar' => $avatar,
                'avatar_url' => 'https://media.indigo24.com/avatars/',
                'text' => $message['text'],
                'time' => $message['time'],
                'write' => $message['write'] ?? '0',
            ];
        }
        return $responseData;
    }

    public static function deleteExtraSpaces(string $text)
    {
        return preg_replace('#\s{2,}#', ' ', $text);
    }

    /**
     * @param Redis $redis
     * @param array $data
     * @param string $messageRedisKey
     * @return string
     */
    public static function create(Redis $redis, array $data,string $messageRedisKey): string
    {
        $redis->hSet($messageRedisKey, 'user_id', $data['user_id']);
        $redis->hSet($messageRedisKey, 'text', $data['text']);
        $redis->hSet($messageRedisKey, 'chat_id', $data['chat_id']);
        $redis->hSet($messageRedisKey, 'status', self::MESSAGE_NO_WRITE_STATUS);
        $redis->hSet($messageRedisKey, 'time', time());
        $redis->hSet($messageRedisKey, 'type', $data['message_type']);

        return $messageRedisKey;

    }

    /**
     * @param Redis $redis
     * @param int $chatId
     * @param string $messageRedisKey
     */
    public static function addMessageInChat(Redis $redis, int $chatId, string $messageRedisKey): void
    {
        // увеличиваю количество непрочитанных сообщений на +1
        $redis->incrBy("chat:unwrite:count:{$chatId}", 1);
        $redis->zAdd("chat:{$chatId}", ['NX'], time(), $messageRedisKey);
    }

    /**
     * @param Redis $redis
     * @param int $chatId
     */
    public static function cleanFirstMessageInRedis(Redis $redis, int $chatId): void
    {
        if ($redis->zCount("chat:{$chatId}", '-inf', '+inf') > ChatController::AVAILABLE_COUNT_MESSAGES_IN_REDIS) {
            $firstMessage = $redis->zRange("chat:{$chatId}", 0, 0)[0];
            $redis->zRem("chat:{$chatId}", $firstMessage);
        }
    }

    /**
     * @param array $data
     * @param int $messageTime
     * @param string $messageRedisKey
     * @param Redis $redis
     * @return array
     */
    public static function getResponseDataForCreateMessage(array $data, string $messageRedisKey, Redis $redis): array
    {
        return [
            'status' => 'true',
            'write' => self::MESSAGE_NO_WRITE_STATUS,
            'chat_id' => $data['chat_id'],
            'message_id' => $messageRedisKey,
            'user_id' => $data['user_id'],
            'time' => $data['message_time'],
            'avatar' => $redis->get("user:avatar:{$data['user_id']}"),
            'user_name' => $redis->get("user:name:{$data['user_id']}"),
        ];
    }

}