<?php


namespace Controllers;


use Carbon\Carbon;
use Helpers\PhoneHelper;
use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Redis;

class UserController
{
    private $redis;

    const USER_ONLINE = 1;

    public function __construct()
    {
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1',6379);
    }


    public function checkExist(array $data)
    {
        $phone = $data['phone'];

        $phone = PhoneHelper::replaceForSeven($phone);

        $checkExist = $this->redis->zRangeByScore('users:phones',$phone,$phone,['withscores' => true]);
        if (count($checkExist)) {
            $userId = array_key_first($checkExist);
            $avatar =$this->redis->get("user:avatar:{$data['user_id']}");

            $chatId = $this->redis->get("private:{$userId}:{$data['user_id']}");
            $chatId = $chatId == false ? $this->redis->get("private:{$data['user_id']}:{$userId}") : $chatId;
            $online = UserHelper::checkOnline($userId,$this->redis);
            $name = $this->redis->get("user:name:{$userId}");
            $this->redis->close();

            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],[
                'status' => 'true',
                'phone' =>strval($data['phone']),
                'user_id' => $userId,
                'avatar' =>$avatar,
                'name' =>$name,
                'avatar_url' => 'https://media.indigo24.com/avatars/',
                'chat_id' =>$chatId,
                'online' => $online
            ]);
        }
        $this->redis->close();
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

        $userId = $data['user_id'];
        $chatId = $data['chat_id'];

        $notifyUsers = $this->redis->zRange("chat:members:{$chatId}",0,-1);

        return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers,[
            'user_id' => $userId,
            'writing' => '1',
            'chat_id' => $chatId,
        ]);

    }

    public function checkOnline(array $data)
    {

        $usersIds = explode(',',$data['users_ids']);

        $responseData = [];
        foreach ($usersIds as $userId) {

            $online = UserHelper::checkOnline($userId,$this->redis);

            $responseData[] = [
              'user_id' => $userId,
              'online' => $online
            ];
        }
        $this->redis->close();
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],$responseData);


    }




}