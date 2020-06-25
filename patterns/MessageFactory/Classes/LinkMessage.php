<?php


namespace Patterns\MessageFactory\Classes;


use Helpers\MessageHelper;
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

        dump($linksData);

        $messageData['text'] = $messageText;
        $messageData['attachments'] = json_decode($linksData);
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
}