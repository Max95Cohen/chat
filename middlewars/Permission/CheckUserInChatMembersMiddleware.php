<?php

namespace Middlewars\Permission;

use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Middlewars\Interfaces\BaseMiddlewareInterface;
use Redis;
use Traits\RedisTrait;

class CheckUserInChatMembersMiddleware implements BaseMiddlewareInterface
{
    private bool $next = false;

    use RedisTrait;

    public function handle(array $data)
    {
        if (UserHelper::CheckUserInChatMembers($data['user_id'], $data['chat_id'], $this->redis) == false) {
            $this->redis->close();
            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], ["success" => false, "message" => 'пользователя нет в участниках чата']);
        }
        $this->redis->close();
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