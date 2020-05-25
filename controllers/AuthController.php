<?php


namespace Controllers;
use Redis;

class AuthController
{

    public function init(array $params)
    {
        $userId = $params['userID'];
        $connectionId = $params['connection_id'];
        $token = $params['userToken'];

        $redis = new Redis();
        $redis->connect('127.0.0.1',6379);

        $checkToken = $redis->hGet($userId,'unique');

        if ($token == 'testUserToken' || $token == $checkToken) {
            return [
             'authorize' => false
            ];
        }



        $redis->set("connection:user:{$userId}",$params['connection_id']);

        return [
           'user_id' => $userId,
           'connection_id' => $connectionId
        ];

    }



}