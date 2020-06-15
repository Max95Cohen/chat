<?php


use Helpers\UserHelper;
use Midlewars\Interfaces\BaseMiddlewareInterface;

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
        var_dump(UserHelper::CheckUserToken($data['userToken'],$data['user_id'],$this->redis));
        if (UserHelper::CheckUserToken($data['userToken'],$data['user_id'],$this->redis) == false) {
            $this->redis->close();
            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], ["success" => false, "message" => 'нужно авторизоваться токен устарел']);
        }

        $this->next =true;
        $this->redis->close();
        return $this->next;

    }
}