<?php


namespace Patterns\MessageFactory\Classes;


use Helpers\MessageHelper;
use Illuminate\Database\Capsule\Manager;
use Patterns\MessageFactory\Factory;
use Patterns\MessageFactory\Interfaces\MessageInterface;
use Redis;

class ReplyMessage implements MessageInterface
{

    /**
     * @param Redis $redis
     * @param string $redisKey
     * @param array $data
     */
    public function addExtraFields(Redis $redis, string $redisKey, array $data): void
    {
        $redis->hSet($redisKey,'reply_message_id',$data['message_id']);
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
        $messageData['type'] = MessageHelper::REPLY_MESSAGE_TYPE;

        $originalMessageType = $redis->hGet($data['message_id'],'type');
        $originalMessageType = $originalMessageType === false ? Manager::table('messages')->where('id',$data['message_id'])->value('type') : $originalMessageType;

        $messageData['reply_data'] = Factory::getItem($originalMessageType)->getOriginalDataForReply($data['message_id'],$redis);

        return $messageData;
    }
}