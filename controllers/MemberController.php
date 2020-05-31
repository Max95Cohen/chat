<?php


namespace Controllers;


use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Redis;

class MemberController
{
    private $redis;


    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);

    }


    public function getChatMembers(array $data)
    {
        $chatId = $data['chat_id'];

        $membersId = $this->redis->zRangeByScore("chat:members:{$chatId}",0,3,['withscores' => true]);
        $responseData = [];


        foreach ($membersId as $memberId => $role) {
            $online = UserHelper::checkOnline($memberId,$this->redis);

            $responseData[] =[
                'user_id' => $memberId,
                'chat_id' => $chatId,
                'avatar' => $this->redis->get("user:avatar:$memberId"),
                'user_name' => $this->redis->get("user:name:$memberId"),
                'online' => $online,
                'role' => strval($role)
            ];
        }

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],$responseData);


    }

}