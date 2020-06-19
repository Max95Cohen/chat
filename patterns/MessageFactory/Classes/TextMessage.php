<?php

namespace Patterns\MessageFactory\Classes;

use Helpers\MessageHelper;
use Illuminate\Database\Capsule\Manager;
use Patterns\MessageFactory\Interfaces\MessageInterface;
use Redis;

class TextMessage implements MessageInterface
{

    /**
     * @param Redis $redis
     * @param string $redisKey
     * @param array $data
     */
    public function addExtraFields(Redis $redis, string $redisKey, array $data): void
    {
        // текст сообщения без лишних пробелов

        $messageTextWithNotDoubleSpaces = MessageHelper::deleteExtraSpaces($data['text']);

        $redis->hSet($redisKey, 'text', $messageTextWithNotDoubleSpaces);
        $redis->hSet($redisKey, 'type', MessageHelper::TEXT_MESSAGE_TYPE);
    }

    /**
     * @param array $data
     * @param string $messageRedisKey
     * @param Redis $redis
     * @return array
     */
    public function returnResponseDataForCreateMessage(array $data, string $messageRedisKey, Redis $redis): array
    {
        $messageData = MessageHelper::getResponseDataForCreateMessage($data, $messageRedisKey, $redis);

        $messageData['text'] = $data['text'];
        $messageData['type'] = MessageHelper::TEXT_MESSAGE_TYPE;
        return $messageData;
    }

    /**
     * @param array $data
     * @param Redis $redis
     */
    public function editMessage(array $data, Redis $redis): array
    {
        MessageHelper::editInRedis($data, $redis);
        MessageHelper::editInMysql($data);

        return [
            'message_id' => $data['message_id'],
            'status' => MessageHelper::MESSAGE_EDITED_STATUS,
            'chat_id' => $data['chat_id'],
            'text' => $data['text'],
            'user_id' => $data['user_id']
        ];
    }

    /**
     * @param array $data
     * @param Redis $redis
     * @return array
     */
    public function deleteMessage(array $data, Redis $redis): array
    {
        MessageHelper::deleteMessageInRedis($data, $redis);
        MessageHelper::updateMessageStatusInMysql($data, MessageHelper::MESSAGE_DELETED_STATUS);

        return [
            'message_id' => $data['message_id'],
            'status' => MessageHelper::MESSAGE_DELETED_STATUS,
            'chat_id' => $data['chat_id'],
            'user_id' => $data['user_id'],
        ];

    }

    /**
     * @param array $data
     * @param Redis $redis
     * @return array
     */
    public function deleteOne(array $data, Redis $redis): array
    {
        $messageId = $data['message_id'];

        $checkInRedis = $redis->exists($messageId);

        if ($checkInRedis) {
            $redis->hMSet($messageId, ['status' => MessageHelper::MESSAGE_DELETED_SELF_STATUS]);
            $redis->set("self:deleted:{$messageId}", 1);
        }

        if (MessageHelper::checkMessageExistInMysql($messageId)) {
            MessageHelper::updateMessageStatusInMysql($data, MessageHelper::MESSAGE_DELETED_SELF_STATUS);
        }

        return [
            'message_id' => $data['message_id'],
            'status' => true,
            'user_id' => $data['user_id']
        ];

    }

    /**
     * @param $messageId
     * @param Redis $redis
     * @return array
     */
    public function getOriginalDataForReply($messageId, Redis $redis)
    {
        $messageDataInRedis = $redis->hGetAll($messageId);

        $messageData = $messageDataInRedis == false
            ? Manager::table('messages')->where('id', $messageId)->first(['id', 'user_id', 'text'])->toArray()
            : $messageDataInRedis;

        return [
            'message_id' => $messageId,
            'text' => $messageData['text'],
            'type' => MessageHelper::TEXT_MESSAGE_TYPE,
            'user_avatar' => $redis->get("user:avatar:{$messageData['user_id']}"),
            'user_name' => $redis->get("user:name:{$messageData['user_id']}"),
            'user_id' => $messageData['user_id'],
        ];
    }


}