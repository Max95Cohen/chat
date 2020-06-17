<?php


namespace Helpers;
use Carbon\Carbon;
use Controllers\ChatController;
use Controllers\UserController;
use Redis;

class UserHelper
{

    /**
     * @param $userId
     * @param $token
     * @return bool|null
     */
    public static function checkToken($userId, $token)
    {
        $redis = new Redis();
        $redis->connect('127.0.0.1',6379);

        return $redis->hGet($userId,'token') == $token ? true : null;

    }

    public static function checkOnline($userId,Redis $redis)
    {
        $offline = $redis->get("user:last:visit:{$userId}");
        $lastVisitDate = 'online';

        if ($offline != UserController::USER_ONLINE && $offline !=false) {
            $date = date('Y-m-d H:i:s',$offline);
            $messageCreatedDate = new Carbon($date);
            $lastVisitDate =$messageCreatedDate->diffForHumans(Carbon::now());

            $lastVisitDate = preg_replace('#до#','назад',$lastVisitDate);

        }

        if($offline ==false){
            $lastVisitDate = 'offline';
        }


        return $lastVisitDate;

    }

    /**
     * @param string $frontEndToken
     * @param int $userId
     * @param Redis $redis
     * @return bool
     */
    public static function CheckUserToken(string $frontEndToken, int $userId, Redis $redis) :bool
    {
        return $redis->hGet("Customer:{$userId}",'unique') == $frontEndToken;
    }


    /**
     * @param int $userId
     * @param int $chatId
     * @param Redis $redis
     * @return bool
     */
    public static function CheckUserInChatMembers(int $userId, int $chatId, Redis $redis)
    {
        $chatMembers = $redis->zRangeByScore("chat:members:{$chatId}",0,'+inf');

        return in_array($userId,$chatMembers);
    }


    /**
     * @param int $userId
     * @param int $chatId
     * @param Redis $redis
     * @return bool
     */
    public static function checkPrivilegesForAdminAndOwner(int $userId, int $chatId, Redis $redis)
    {
        $chatMembers = $redis->zRangeByScore("chat:members:{$chatId}",ChatController::OWNER,'+inf',['withscores' => true]);
        $userRole = !is_null($chatMembers[$userId]) ? intval($chatMembers[$userId]) : null;

        return in_array($userRole,ChatController::getRolesForAdministrators());

    }


}