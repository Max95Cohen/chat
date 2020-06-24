<?php


namespace Patterns\MessageFactory\Classes;


use Helpers\MessageHelper;
use Patterns\MessageFactory\Interfaces\MessageInterface;
use Redis;

class MoneyMessage
{

    /**
     * @param array $data
     * @param Redis $redis
     */
    public function create(array $data, Redis $redis)
    {
        $redis->multi();
        $messageId = $redis->incrBy("user:message:{$data['user_id']}", 1);
        $messageRedisKey = "message:{$data['user_id']}:$messageId";

        $data['chat_id'] = MessageHelper::getChatIdByUsersId($data['from_user_id'],$data['to_user_id']);

        MessageHelper::create($redis,$data,$messageRedisKey);

        $redis->hSet($messageRedisKey,'text',$data['amount']);
        $redis->exec();
    }


}