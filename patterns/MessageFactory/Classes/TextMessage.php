<?php

namespace Patterns\MessageFactory\Classes;

use Helpers\MessageHelper;
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
        $messageData = MessageHelper::getResponseDataForCreateMessage($data,$messageRedisKey,$redis);

        $messageData['text'] = $data['text'];
        $messageData['type'] = MessageHelper::TEXT_MESSAGE_TYPE;
        return $messageData;
    }


}