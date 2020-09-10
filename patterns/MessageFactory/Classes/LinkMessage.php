<?php


namespace Patterns\MessageFactory\Classes;


use Helpers\MessageHelper;
use Illuminate\Database\Capsule\Manager;
use Patterns\MessageFactory\Interfaces\MessageInterface;
use Redis;

class LinkMessage implements MessageInterface
{

    /**
     * @param array $data
     * @param string $messageRedisKey
     * @param Redis $redis
     * @return array
     */
    public function returnResponseDataForCreateMessage(array $data, string $messageRedisKey, Redis $redis): array
    {
        $messageData = MessageHelper::getResponseDataForCreateMessage($data, $messageRedisKey, $redis);

        //@TODO отрефакторить вынести в 1 метод
        $allLinks = [];
        $linksData = [];
        preg_match_all('#http[s]{0,1}:\/\/\S+#', $data['text'], $allLinks,PREG_PATTERN_ORDER);
        $allLinks = $allLinks[0] ?? null;
        $messageText = $data['text'];
        if ($allLinks) {
            foreach ($allLinks as $link) {
                $linksData[]['link'] = trim($link);
                $messageText = str_replace($link,'',$messageText);
            }
        }

        $messageData['text'] = $messageText;
        $messageData['attachments'] = json_encode($linksData);
        $messageData['type'] = MessageHelper::LINK_MESSAGE_TYPE;
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

    public function addExtraFields(Redis $redis, string $redisKey, array $data): void
    {
        $redis->hSet($redisKey,'attachments',json_encode($data['attachments']));
        $redis->hSet($redisKey,'type',MessageHelper::LINK_MESSAGE_TYPE);
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
            ? Manager::table('messages')->where('id', $messageId)->first(['id', 'user_id', 'attachments'])->toArray()
            : $messageDataInRedis;
        //@TODO преписать через хелперскую функию вынести туда общие для всех классов поля и черех ... собирать в 1 массив

        $attachments = json_decode($messageData['attachments'],true);

        return [
            'message_id' => $messageId,
            'type' => MessageHelper::LINK_MESSAGE_TYPE,
            'user_avatar' => $redis->get("user:avatar:{$messageData['user_id']}"),
            'user_name' => $redis->get("user:name:{$messageData['user_id']}"),
            'user_id' => $messageData['user_id'],
            'attachments' => array_shift($attachments),
            'message_text_for_type' => MessageHelper::getAttachmentTypeString(MessageHelper::LINK_MESSAGE_TYPE),
            'is_deleted' => $messageData['status'] == MessageHelper::MESSAGE_DELETED_STATUS,
        ];
    }

}