<?php

namespace Middlewars\Auth;
use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Middlewars\Interfaces\BaseMiddlewareInterface;
use Redis;

class CheckUserTokenMiddleware implements BaseMiddlewareInterface
{

    public $next = false;
    private $redis;


    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1',6379);
    }

    public function handle(array $data)
    {
        if (UserHelper::CheckUserToken($data['userToken'],$data['user_id'],$this->redis) == false) {
            $this->redis->close();
            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], ["success" => false, "message" => 'нужно авторизоваться токен устарел', 'logout'  => true]);
        }

        $this->setNext(true);
        $this->redis->close();

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