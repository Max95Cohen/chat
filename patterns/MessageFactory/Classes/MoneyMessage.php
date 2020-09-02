<?php


namespace Patterns\MessageFactory\Classes;


use Helpers\MessageHelper;
use Illuminate\Database\Capsule\Manager;
use Patterns\MessageFactory\Interfaces\MessageInterface;
use Redis;

class MoneyMessage implements MessageInterface
{

    /**
     * @param array $data
     * @param string $messageRedisKey
     * @param Redis $redis
     * @return array
     */
    public function returnResponseDataForCreateMessage(array $data, string $messageRedisKey, Redis $redis): array
    {
        $messageData = MessageHelper::getResponseDataForCreateMessage($data,$messageRedisKey,$redis);

        $anotherUserId = $redis->hGet($messageRedisKey,'another_user_id');

        $messageData['type'] = MessageHelper::MONEY_MESSAGE_TYPE;
        $messageData['text'] = $redis->hGet($messageRedisKey,'text');
        $messageData['another_user_id'] = $anotherUserId;
        $messageData['another_user_name'] = $redis->get("user:name:{$anotherUserId}");
        $messageData['another_user_avatar'] = $redis->get("user:avatar:{$anotherUserId}");


        return $messageData;
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

        $anotherUserId = $messageData['another_user_id'];
        return [
            'message_id' => $messageId,
            'type' => MessageHelper::MONEY_MESSAGE_TYPE,
            'user_avatar' => $redis->get("user:avatar:{$messageData['user_id']}"),
            'another_user_id' => $anotherUserId,
            'another_user_avatar' => $redis->get("user:avatar:{$anotherUserId}"),
            'another_user_name' => $redis->get("user:name:{$anotherUserId}"),
            'user_name' => $redis->get("user:name:{$messageData['user_id']}"),
            'user_id' => $messageData['user_id'],
            'message_text_for_type' => MessageHelper::getAttachmentTypeString(MessageHelper::MONEY_MESSAGE_TYPE),
            'text' => $messageData['text'],
            'is_deleted' => $messageData['status'] == MessageHelper::MESSAGE_DELETED_STATUS,
        ];
    }


    /**
     * @param Redis $redis
     * @param string $redisKey
     * @param array $data
     */
    public function addExtraFields(Redis $redis, string $redisKey, array $data): void
    {
        $userId = $data['user_id'];

        $paymentChatToken = $data['payment_chat_token'];

        // тут достаем данные о совершенном ранее платаже
        $paymentChatMoneyRedisKey = "token:money:chat:{$userId}:{$paymentChatToken}";

        $paymentChatMoneyData = $redis->hGetAll($paymentChatMoneyRedisKey);
        if ($paymentChatMoneyData) {

            MessageHelper::create($redis,$data,$redisKey);

            $redis->hSet($redisKey,'another_user_id',$paymentChatMoneyData['another_user_id']);
            $redis->hSet($redisKey,'text',$paymentChatMoneyData['amount']);
            $redis->hSet($redisKey,'type',MessageHelper::MONEY_MESSAGE_TYPE);

        }
        $redis->del($paymentChatMoneyRedisKey);


    }
}