<?php


namespace Controllers;


use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Redis;

class MemberController
{

    public function getChatMembers(array $data)
    {
        $chatId = $data['chat_id'];
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);


        $membersId = $redis->zRangeByScore("chat:members:{$chatId}",0,3,['withscores' => true]);
        $responseData = [];


        foreach ($membersId as $memberId => $role) {
            $online = UserHelper::checkOnline($memberId,$redis);

            $responseData[] =[
                'user_id' => $memberId,
                'chat_id' => $chatId,
                'avatar' => $redis->get("user:avatar:$memberId"),
                'user_name' => $redis->get("user:name:$memberId"),
                'online' => $online,
                'role' => strval($role)
            ];
        }

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],$responseData);


    }

}