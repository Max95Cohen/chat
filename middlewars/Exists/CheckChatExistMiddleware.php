<?php

namespace Middlewars\Exists;

use Helpers\ResponseFormatHelper;
use Illuminate\Database\Capsule\Manager;
use Middlewars\Interfaces\BaseMiddlewareInterface;

class CheckChatExistMiddleware implements BaseMiddlewareInterface
{
    public $next = false;


    public function handle(array $data)
    {
        $exist = Manager::table('chats')->where('id',$data['chat_id'])->exists();

        if (!$exist) {
            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], ["success" => false, "message" => 'чат не существует']);
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