<?php


namespace Middlewars\Permission;


use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Middlewars\Interfaces\BaseMiddlewareInterface;
use Redis;

class CheckPrivilegesForAddGroupChat implements BaseMiddlewareInterface
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
        // проверяем привилегии добавлять может только админ или владалец группы

        if (UserHelper::checkPrivilegesForAdminAndOwner($data['user_id'],$data['chat_id'],$this->redis) == false) {
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