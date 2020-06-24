<?php


namespace Middlewars\Permission;


use Helpers\ResponseFormatHelper;
use Illuminate\Database\Capsule\Manager;
use Middlewars\Interfaces\BaseMiddlewareInterface;
use Redis;

class CheckPrivilegesForMessageMiddleware implements BaseMiddlewareInterface
{

    private bool $next = false;
    private Redis $redis;

    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
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

    /**
     * @param array $data
     * @return array[]
     */
    public function handle(array $data)
    {
        $messageOwnerCheckInRedis = $this->redis->hGet($data['message_id'], 'user_id');
        $messageOwner = $messageOwnerCheckInRedis === false ? Manager::table('messages')->where('id',$data['message_id'])->value('user_id') : $messageOwnerCheckInRedis;
        if ($messageOwner != $data['user_id']) {
            $this->redis->close();
            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], ["success" => false, "message" => 'нельзя редактировать чужие сообщения']);
        }
        $this->setNext(true);

    }
}