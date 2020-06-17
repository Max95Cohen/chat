<?php


namespace Helpers;

use Controllers\ChatController;
use Illuminate\Database\Capsule\Manager;
use Patterns\MessageFactory\Factory;
use Redis;

class MessageHelper
{

    const TEXT_MESSAGE_TYPE = 0;
    const IMAGE_MESSAGE_TYPE = 1;
    const DOCUMENT_MESSAGE_TYPE = 2;
    const VOICE_MESSAGE_TYPE = 3;
    const VIDEO_MESSAGE_TYPE = 4;
    const SYSTEM_MESSAGE_TYPE = 7;

    const MESSAGE_NO_WRITE_STATUS = 0;
    const MESSAGE_WRITE_STATUS = 1;
    const MESSAGE_EDITED_STATUS = 2;
    const MESSAGE_DELETED_STATUS = -1;

    const AVATAR_URL = 'https://indigo24.xyz/uploads/avatars/';


    public static function getMessageIncorrectFormat($allMessages, Redis $redis)
    {
        $responseData = [];
        foreach ($allMessages as $messageId => $message) {
            $attachmentUrl = null;
            if (!is_array($message)) {
                $message = (array)$message;
            }

            $avatar = $redis->get("user:avatar:{$message['user_id']}");
            $name = $redis->get("user:name:{$message['user_id']}");

            if ($message['type'] == MessageHelper::IMAGE_MESSAGE_TYPE) {
                $attachmentUrl = MEDIA_URL . '/images/';
            }
            if ($message['type'] == MessageHelper::VIDEO_MESSAGE_TYPE) {
                $attachmentUrl = MEDIA_URL . '/video/';
            }
            if ($message['type'] == MessageHelper::VOICE_MESSAGE_TYPE) {
                $attachmentUrl = MEDIA_URL . '/voice/';
            }
            if ($message['type'] == MessageHelper::DOCUMENT_MESSAGE_TYPE) {
                $attachmentUrl = MEDIA_URL . '/documents/';
            }


            $responseData[] = [
                'id' => $message['id'],
                'user_id' => $message['user_id'],
                'user_name' => $name,
                'avatar' => $avatar,
                'avatar_url' => self::AVATAR_URL,
                'text' => $message['text'],
                'time' => $message['time'],
                'type' => $message['type'] ?? 0,
                'write' => $message['write'] ?? '0',
                'attachments' => $redis->hGet($messageId, 'attachments'),
                'attachment_url' => $attachmentUrl,
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
    public static function create(Redis $redis, array $data, string $messageRedisKey): string
    {
        $redis->hSet($messageRedisKey, 'user_id', $data['user_id']);
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
            'avatar_url' => self::AVATAR_URL,
            'user_name' => $redis->get("user:name:{$data['user_id']}"),
        ];
    }


    /**
     * @param int $type
     * @return string
     */
    public static function getAttachmentTypeString(int $type)
    {
        switch ($type) {
            case self::IMAGE_MESSAGE_TYPE:
                return "Изображение";
            case self::DOCUMENT_MESSAGE_TYPE:
                return "Документ";
            case self::VOICE_MESSAGE_TYPE:
                return "Голосовое сообщение";
            case self::VIDEO_MESSAGE_TYPE:
                return "видео";
        }
    }

    /**
     * @param array $data
     * @param Redis $redis
     */
    public static function editInRedis(array $data, Redis $redis): void
    {
        $checkExistInRedis = $redis->hGetAll($data['message_id']);

        if ($checkExistInRedis) {
            $redis->hMSet($data['message_id'], ['text' => $data['text']]);
            $redis->hMSet($data['edited'], self::MESSAGE_EDITED_STATUS);
        }

    }

    /**
     * @param array $data
     */
    public static function editInMysql(array $data): void
    {
        Manager::table('messages')
            ->where('redis_id', $data['message_id'])
            ->orWhere('id', $data['message_id'])
            ->update([
                'text' => $data['text'],
                'status' => self::MESSAGE_EDITED_STATUS,
            ]);
    }

    /**
     * @param array $data
     * @param Redis $redis
     */
    public static function deleteMessageInRedis(array $data, Redis $redis): void
    {
        $redis->hMSet($data['message_id'], ['status' => self::MESSAGE_DELETED_STATUS]);
    }

    /**
     * @param array $data
     */
    public static function deleteMessageInMysql(array $data) :void
    {
        $sql = Manager::table('messages');
        $messageId = intval($data['message_id']);

        if ($messageId !== 0) {
            $sql->where('id', $data['message_id']);

        } else {
            $sql->orWhere('redis_id', $data['message_id']);
        }

        $sql->update([
                'status' => self::MESSAGE_DELETED_STATUS,
            ]);
    }

}