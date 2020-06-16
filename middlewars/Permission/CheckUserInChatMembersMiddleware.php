<?php

namespace Middlewars\Permission;

use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Middlewars\Interfaces\BaseMiddlewareInterface;
use Redis;

class CheckUserInChatMembersMiddleware implements BaseMiddlewareInterface
{
    private bool $next = false;
    private Redis $redis;

    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1',6379);
    }


    public function handle(array $data)
    {
        $this->redis->get("user:phone:{$data['user_id']}");
        if (UserHelper::CheckUserInChatMembers($data['user_id'],$data['chat_id'],$this->redis) == false) {
            $this->redis->close();
            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], ["success" => false, "message" => 'пользователя нет в участниках чата']);
        }
        $this->setNext(true);

    }

    /**
     * @return bool
     */
    public function isNext(): bool
    {
        return $this->next;
    }

    /**
     * @param bool $next
     */
    public function setNext(bool $next): void
    {
        $this->next = $next;
    }


}