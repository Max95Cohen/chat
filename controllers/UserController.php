<?php

namespace Controllers;

use Carbon\Carbon;
use Helpers\ConfigHelper;
use Helpers\Helper;
use Helpers\MessageHelper;
use Helpers\PhoneHelper;
use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Redis;
use Traits\RedisTrait;
use Illuminate\Database\Capsule\Manager;

class UserController
{
    use RedisTrait;

    const USER_ONLINE = 1;

    public function checkExist(array $data)
    {
        $phone = $data['phone'];

        $phone = PhoneHelper::replaceForSeven($phone);

        $userID = $this->redis->get("user:phone:{$phone}");

        $foundUser = false;

        if ($userID) {
            $userID = (int)$userID;
            $avatar = $this->redis->get("user:avatar:{$userID}");

            $online = UserHelper::checkOnline($userID, $this->redis);

            $name = $this->redis->get("user:name:{$userID}");

            $foundUser = true;
        } else {
            $capsule = new Manager;

            $config = ConfigHelper::getDbConfig('mobile_db');

            $capsule->addConnection([
                'driver' => $config['driver'],
                'host' => $config['host'],
                'database' => $config['database'],
                'username' => $config['username'],
                'password' => $config['password'],
                'charset' => $config['charset'],
                'collation' => $config['collation'],
                'prefix' => $config['prefix'],
            ]);

            $capsule->setAsGlobal();

            $user = Manager::table('customers')->where('phone', $phone)
                ->first(['id', 'name', 'avatar', 'phone']);

            $phoneInCorrectFormat = PhoneHelper::replaceForSeven($user->phone);

            $this->redis->set("user:avatar:{$user->id}", $user->avatar);
            $this->redis->set("user:name:{$user->id}", $user->name);
            $this->redis->set("userId:phone:{$user->id}", $user->phone);
            $this->redis->set("user:phone:{$phoneInCorrectFormat}", $user->id);

            $avatar = $user->avatar;
            $name = $user->name;
            $online = false;

            $foundUser = true;
        }

        $chatId = $this->redis->get("private:{$userID}:{$data['user_id']}");
        $chatId = $chatId == false ? $this->redis->get("private:{$data['user_id']}:{$userID}") : $chatId;

        $chatDeletedByUser = $this->redis->get("chat:deleted:{$data['user_id']}:{$chatId}");

        if ($chatDeletedByUser) {
            $chatId = false;
        }

        $this->redis->close();

        if ($foundUser) {
            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], [
                'status' => 'true',
                'phone' => strval($data['phone']),
                'user_id' => $userID,
                'avatar' => $avatar,
                'name' => $name,
                'avatar_url' => MessageHelper::AVATAR_URL,
                'chat_id' => $chatId,
                'online' => $online
            ]);
        }

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
        $chatMembers = $this->redis->zRangeByScore("chat:members:{$chatId}", 0, ChatController::OWNER);
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

    /**
     * @param array $data
     * @param Redis $redis
     * @return array
     */
    public function checkUserById(array $data): array
    {
        $checkUserId = $data['check_user_id'] ?? null;

        $chatId = $this->redis->get("private:{$checkUserId}:{$data['user_id']}");
        $chatId = $chatId == false ? $this->redis->get("private:{$data['user_id']}:{$checkUserId}") : $chatId;

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], [
            'status' => 'true',
            'phone' => $this->redis->get("userId:phone:{$data['check_user_id']}"),
            'user_id' => (int)$checkUserId,
            'avatar' => $this->redis->get("user:avatar:{$checkUserId}"),
            'name' => $this->redis->get("user:name:{$checkUserId}"),
            'avatar_url' => MessageHelper::AVATAR_URL,
            'chat_id' => $chatId,
            'online' => UserHelper::checkOnline($checkUserId, $this->redis)
        ]);
    }
}
