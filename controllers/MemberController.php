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

        $membersId = $this->redis->zRangeByScore("chat:members:{$chatId}", 0, 3, ['withscores' => true]);
        $responseData = [];


        foreach ($membersId as $memberId => $role) {
            $online = UserHelper::checkOnline($memberId, $this->redis);

            $responseData[] = [
                'user_id' => $memberId,
                'chat_id' => $chatId,
                'avatar' => $this->redis->get("user:avatar:$memberId"),
                'user_name' => $this->redis->get("user:name:$memberId"),
                'online' => $online,
                'role' => strval($role)
            ];
        }

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseData);


    }

    public function changeUserPrivileges(array $data)
    {
        $chatId = $data['chat_id'];
        $userId = $data['user_id'];
        $role = $data['role'];

        $membersForChange = explode(',', $data['members']);
        $chatMembers = $this->redis->zRangeByScore("chat:members:{$chatId}", ChatController::OWNER, 3);

        $checkAdmin = array_search($userId, $chatMembers);
        $checkMembersForChange = array_intersect($membersForChange, $chatMembers);
        $changeUsers = [];

        if ($checkAdmin === ChatController::OWNER && in_array($role, ChatController::getRolesForOwner())) {
            foreach ($checkMembersForChange as $memberForChange) {
                $this->redis->zAdd("chat:members:{$chatId}", ['CH'], $role, $memberForChange);
                $changeUsers[] = [
                    'user_id' => $memberForChange,
                    'role' => $role,
                ];
            }

            return ResponseFormatHelper::successResponseInCorrectFormat($chatMembers, [
                'status' => 'true',
                'users' => $changeUsers,
                'chat_id' => $chatId
            ]);

        }

    }

    public function deleteMembers(array $data)
    {
        $chatId = $data['chat_id'];
        $userId = $data['user_id'];

        $deletedMembers = $data['members'];

        $chatMembers = $this->redis->zRangeByScore("chat:members:{$chatId}", ChatController::OWNER, '+inf', ['withscores' => true]);

        foreach ($deletedMembers as $deletedMember) {
            $deletedUserRole

        }

    }


}