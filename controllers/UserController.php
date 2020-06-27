<?php


namespace Controllers;


use Carbon\Carbon;
use Helpers\PhoneHelper;
use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Redis;
use Traits\RedisTrait;

class UserController
{
    use RedisTrait;


    const USER_ONLINE = 1;

    public function checkExist(array $data)
    {
        $phone = $data['phone'];

        $phone = PhoneHelper::replaceForSeven($phone);

        $checkExist = $this->redis->zRangeByScore('users:phones', $phone, $phone, ['withscores' => true]);
        if (count($checkExist)) {
            $userId = array_key_first($checkExist);
            $avatar = $this->redis->get("user:avatar:{$data['user_id']}");

            $chatId = $this->redis->get("private:{$userId}:{$data['user_id']}");
            $chatId = $chatId == false ? $this->redis->get("private:{$data['user_id']}:{$userId}") : $chatId;
            $online = UserHelper::checkOnline($userId, $this->redis);
            $name = $this->redis->get("user:name:{$userId}");
            $this->redis->close();

            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], [
                'status' => 'true',
                'phone' => strval($data['phone']),
                'user_id' => $userId,
                'avatar' => $avatar,
                'name' => $name,
                'avatar_url' => 'https://media.indigo24.com/avatars/',
                'chat_id' => $chatId,
                'online' => $online
            ]);
        }
        $this->redis->close();
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], [
            'status' => 'false',
            'phone' => $data['phone']
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

        $this->redis->set("user:taping:{$chatId}:{$userId}", 1, 4);
        $chatMembers = $this->redis->zRangeByScore("chat:members:{$chatId}", ChatController::OWNER, 100);
        $tapingMembers = [];

        foreach ($chatMembers as $member) {

            if ($this->redis->get("user:taping:{$chatId}:{$member}")) {
                $tapingMembers[] = [
                    'name' => $this->redis->get("user:name:{$member}") ?? '',
                    'chat_id' => $chatId,
                    'user_id' => $member,
                    'typing' => 1
                ];

            }

        }
        $notifyUsers = array_diff($chatMembers, [$data['user_id']]);

        $this->redis->close();
        return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers, $tapingMembers);

    }

    public function checkOnline(array $data)
    {

        $usersIds = explode(',', $data['users_ids']);

        $responseData = [];
        foreach ($usersIds as $userId) {

            $online = UserHelper::checkOnline($userId, $this->redis);

            $responseData[] = [
                'user_id' => $userId,
                'online' => $online
            ];
        }
        $this->redis->close();
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseData);


    }


}