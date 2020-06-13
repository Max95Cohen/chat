<?php


use Helpers\UserHelper;
use Midlewars\Interfaces\BaseMiddlewareInterface;

class CheckUserTokenMiddleware implements BaseMiddlewareInterface
{

    public $next = false;


    public function handle(array $data)
    {
        if (UserHelper::CheckUserToken() == false) {
            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], ["success" => false, "message" => 'нужно авторизоваться']);
        }

        $this->next =true;

        return $this->next;

    }
}