<?php


namespace Controllers;

use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Redis;

class AuthController
{

    public function init(array $params)
    {
        $userId = $params['user_id'];
        $connectionId = $params['connection_id'];


        // if (!UserHelper::checkToken($userId, $params['userToken'])) {
//            return [
//                'data' => [
//                    'status' => 'false',
//                ],
//                'notify_users' => [
//                    $userId
//                ],
//            ];
//        }

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
        $redis->zAdd("user:last:visit:{$userId}",['CH'],UserController::USER_ONLINE,$userId);

        $redis->close();

        return ResponseFormatHelper::successResponseInCorrectFormat([$userId], $data);


    }


}