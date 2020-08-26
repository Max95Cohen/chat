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
    const SYSTEM_MESSAGE_DIVIDER_TYPE = 8;
    const GEO_POINT_MESSAGE_TYPE = 9;
    const REPLY_MESSAGE_TYPE = 10;
    const MONEY_MESSAGE_TYPE = 11;
    const LINK_MESSAGE_TYPE = 12;
    const FORWARD_MESSAGE_TYPE = 13;
    const STICKER_MESSAGE_TYPE = 14;

    const MESSAGE_NO_WRITE_STATUS = 0;
    const MESSAGE_WRITE_STATUS = 1;
    const MESSAGE_EDITED_STATUS = 2;
    const MESSAGE_DELETED_STATUS = -1;
    const MESSAGE_DELETED_SELF_STATUS = -2;

    const MESSAGE_COUNT_IN_REDIS = 40;

    const AVATAR_URL = INDIGO_URL.'uploads/avatars/';
    const GROUP_AVATAR_URL ='https://media.chat.indigo24.xyz/media/group/';

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

        $chatId = intval($data['chat_id']);

        return [
            'status' => 'true',
            'write' => self::MESSAGE_NO_WRITE_STATUS,
            'mute' =>$redis->zRangeByScore("u:mute:ch:{$data['user_id']}",$data['chat_id'],$data['chat_id']) == [] ? ChatController::CHAT_UNMUTE : ChatController::CHAT_MUTE,
            'chat_id' => $data['chat_id'],
            'message_id' => $messageRedisKey,
            'user_id' => $data['user_id'],
            'time' => $data['message_time'],
            'avatar' => $redis->get("user:avatar:{$data['user_id']}"),
            'avatar_url' => self::AVATAR_URL,
            'user_name' => $redis->get("user:name:{$data['user_id']}"),
            'chat_name' => ChatHelper::getChatName($data['chat_id'],$data['user_id'],$redis),
            'chat_type' => ChatHelper::getChatType($chatId,$redis)
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
                return "Видео";
            case self::LINK_MESSAGE_TYPE:
                return "ссылка";
            case self::MONEY_MESSAGE_TYPE:
                return "Деньги";
            case self::STICKER_MESSAGE_TYPE:
                return "Стикер";
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
            $redis->hSet($data['message_id'],'edit',1);
        }

    }

    /**
     * @param array $data
     */
    public static function editInMysql(array $data): void
    {
        Manager::table('messages')
            ->where('redis_id', $data['message_id'])
            ->update([
                'text' => $data['text'],
                'edit' => 1,
            ]);
    }

    /**
     * @param array $data
     * @param Redis $redis
     */
    public static function deleteMessageInRedis(array $data, Redis $redis): void
    {
        $redis->hMSet($data['message_id'], ['status' => self::MESSAGE_DELETED_STATUS]);
        $redis->set("all:deleted:{$data['message_id']}",1);
        $redis->zRem("chat:{$data['chat_id']}",$data['message_id']);
    }

    /**
     * @param array $data
     */
    public static function updateMessageStatusInMysql(array $data,$status) :void
    {
        $sql = Manager::table('messages');
        $messageId = intval($data['message_id']);

        if ($messageId !== 0) {
            $sql->where('id', $data['message_id']);

        } else {
            $sql->orWhere('redis_id', $data['message_id']);
        }

        $sql->update([
                'status' => $status,
            ]);

    }

    /**
     * @param $messageId
     * @return bool
     */
    public static function checkMessageExistInMysql($messageId) :bool
    {
        return Manager::table('messages')
            ->where('redis_id',$messageId)
            ->orWhere('id',$messageId)
            ->exists();
    }

    public static function getOriginalMessageDataForReply($messageId, Redis $redis) :array
    {
        $checkMessageInRedis = $redis->exists($messageId);

        if ($checkMessageInRedis) {
            $redis->hGetAll($messageId);

            // тут в зависимости от типа нужно вернуть соответствующие данные

        }

    }


    public static function getChatIdByUsersId( int $userId, int $anotherUserId,Redis $redis)
    {
        $chatId = $redis->get("private:{$userId}:{$anotherUserId}");
        return $chatId === false ? $redis->get("private:{$anotherUserId}:{$userId}") : $chatId;
    }


    /**
     * @return int[]
     */
    public static function getMessageTypes() :array
    {
        return [
            self::TEXT_MESSAGE_TYPE,
            self::IMAGE_MESSAGE_TYPE,
            self::DOCUMENT_MESSAGE_TYPE,
            self::VOICE_MESSAGE_TYPE,
            self::VIDEO_MESSAGE_TYPE,
            self::SYSTEM_MESSAGE_TYPE,
            self::SYSTEM_MESSAGE_DIVIDER_TYPE,
            self::GEO_POINT_MESSAGE_TYPE,
            self::REPLY_MESSAGE_TYPE,
            self::MONEY_MESSAGE_TYPE,
            self::LINK_MESSAGE_TYPE,
            self::FORWARD_MESSAGE_TYPE,
            self::STICKER_MESSAGE_TYPE,
        ];
    }

    /**
     * @param string $messageId
     * @param Redis $redis
     * @return string|null
     */
    public static function getMessageText(string $messageId, Redis $redis)
    {
        $text = $redis->hGet($messageId,'text');
        return $text === false ? "" : $text;
    }

    /**
     * @param int $unReadCount
     * @param int $chatId
     * @param Redis $redis
     * @return mixed
     */
    public static function getLastReadMessageId(int $unReadCount, int $chatId, Redis $redis)
    {
        if ($unReadCount <= self::MESSAGE_COUNT_IN_REDIS) {
            $message = $redis->zRange("chat:{$chatId}",$unReadCount,$unReadCount);

            if ($message) {
                return $message[0];
            }

        }

        if ($unReadCount > self::MESSAGE_COUNT_IN_REDIS) {
            $message = Manager::table("messages")
                ->where('chat_id',$chatId)
                ->skip($unReadCount-1)
                ->first();

            return $message->redis_id;
        }

        $message = $redis->zRange("chat:{$chatId}",-1,-1);
        return $message[0];

    }




}