<?php


namespace Middlewars\Permission;


use Controllers\ChatController;
use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Middlewars\Interfaces\BaseMiddlewareInterface;
use Traits\RedisTrait;

class CheckPrivilegesForDeleteMemberMiddleware implements BaseMiddlewareInterface
{
    use RedisTrait;

    private bool $next = false;


    public function handle(array $data)
    {

        $chatMembers = $this->redis->zRangeByScore("chat:members:{$data['chat_id']}", 0, "+inf", ['withscores' => true]);
        if ($chatMembers[$data['user_id']] == ChatController::OWNER || $chatMembers[$data['user_id']] == ChatController::ADMIN) {

            if ($chatMembers[$data['user_id']] == ChatController::ADMIN && $chatMembers[$data['member_id']] == ChatController::ADMIN) {
                return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], ["status" => false, "message" => 'только владелец может удалять администратора']);
            }

            $this->setNext(true);
        }
        $this->redis->close();
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], ["status" => false, "message" => 'только админ или владелец может удалять из группы']);
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