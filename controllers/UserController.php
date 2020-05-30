<?php


namespace Controllers;


use Carbon\Carbon;
use Helpers\PhoneHelper;
use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Redis;

class UserController
{

    public function checkExist(array $data)
    {
        $phone = $data['phone'];

        $redis = new \Redis();
        $redis->connect('127.0.0.1',6379);
        $phone = PhoneHelper::replaceForSeven($phone);

        $checkExist = $redis->zRangeByScore('users:phones',$phone,$phone,['withscores' => true]);

        if (count($checkExist)) {
            $userId = array_key_first($checkExist);
            $avatar =$redis->get("user:avatar:{$data['user_id']}");

            $chatId = $redis->get("private:{$userId}:{$data['user_id']}");
            $chatId = $chatId == false ? $redis->get("private:{$data['user_id']}:{$userId}") : $chatId;
            $redis->close();


            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],[
                'status' => 'true',
                'phone' =>strval($data['phone']),
                'user_id' => array_key_first($checkExist),
                'avatar' =>$avatar,
                'avatar_url' => 'https://media.indigo24.com/avatars/',
                'chat_id' =>$chatId
            ]);
        }
        $redis->close();
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],[
            'status' => 'false',
            'phone' =>$data['phone']
        ]);

    }

    /**
     * @param array $data
     * @return array[]
     */
    public function writing(array $data)
    {
        $redis = new Redis();
        $redis->connect('127.0.0.1',6379);
        $userId = $data['user_id'];


        $notifyUsers = $redis->zRange("chat:members:{$userId}",0,-1);

        return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers,[
            'user_id' => $userId,
            'writing' => '1'
        ]);

    }

    public function checkOnline(array $data)
    {
        $redis = new Redis();
        $redis->connect('127.0.0.1',6379);


        $usersIds = explode(',',$data['users_ids']);

        $responseData = [];
        foreach ($usersIds as $userId) {

            $online = UserHelper::checkOnline($userId,$redis);

            $responseData[] = [
              'user_id' => $userId,
              'online' => $online
            ];
        }
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],$responseData);


    }




}