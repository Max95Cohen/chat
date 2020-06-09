<?php


namespace Helpers;
use Carbon\Carbon;
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


}