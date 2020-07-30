<?php


namespace Middlewars\Auth;


use Middlewars\Interfaces\BaseMiddlewareInterface;
use Traits\RedisTrait;

class UpdateFcmMiddleware implements BaseMiddlewareInterface
{
    use RedisTrait;

    public $next = false;

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

    public function handle(array $data)
    {
        $fcm = $data['fcm'] ?? null;
        if ($fcm) {
            $this->redis->hSet("Customer:{$data['user_id']}", 'fcm', $data['fcm']);
        }
        $this->next = true;

    }
}