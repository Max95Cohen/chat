<?php


namespace Controllers;

use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Redis;

class AuthController
{

    /**
     * @param array $params
     * @return array[]
     */
    public function init(array $params)
    {
        $userId = $params['user_id'];
        $connectionId = $params['connection_id'];

        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->set("con:{$userId}", $connectionId);

        $data = [
            'status' => 'true',
            'user_id' => $userId,
            'connection_id' => $connectionId,
        ];


        $redis->zAdd("users:connections",['CH'],$connectionId,$userId);

        // удаляем время последнего выхода юзера из приложения, если этого ключа нет пользователь онлайн
        $redis->del("user:last:visit:{$userId}");
        $redis->set("user:last:visit:{$userId}",UserController::USER_ONLINE);

        $redis->close();

        return ResponseFormatHelper::successResponseInCorrectFormat([$userId], $data);


    }


}