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
        $text = $data['text'] ?? null;
        $attachments = $data['attachments'] ?? null;
        if ($text) {
            $redis->hSet($redisKey,'text',$text);
        }
        if ($attachments) {
            $redis->hSet($redisKey,'attachments',$attachments);
        }


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
        $messageData['text'] = $data['text'] ?? null;
        $messageData['attachments'] = $data['attachments'] ?? null;

        $originalMessageType = $redis->hGet($data['message_id'],'type');
        $originalMessageType = $originalMessageType === false ? Manager::table('messages')->where('id',$data['message_id'])->value('type') : $originalMessageType;

        $messageData['reply_data'] = Factory::getItem($originalMessageType)->getOriginalDataForReply($data['message_id'],$redis);

        return $messageData;
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

    public function getOriginalDataForReply($messageId, Redis $redis)
    {
        $messageDataInRedis = $redis->hGetAll($messageId);

        $messageData = $messageDataInRedis == false
            ? Manager::table('messages')->where('id', $messageId)->first(['id', 'user_id', 'text'])->toArray()
            : $messageDataInRedis;
        return [
            'message_id' => $messageId,
            'text' => $messageData['text'],
            'type' => MessageHelper::REPLY_MESSAGE_TYPE,
            'user_avatar' => $redis->get("user:avatar:{$messageData['user_id']}"),
            'user_name' => $redis->get("user:name:{$messageData['user_id']}"),
            'user_id' => $messageData['user_id'],
            'is_deleted' => $messageData['status'] == MessageHelper::MESSAGE_DELETED_STATUS,
        ];
    }
}