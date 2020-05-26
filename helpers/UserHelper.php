<?php


namespace Helpers;
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


}