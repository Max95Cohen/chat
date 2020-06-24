<?php


namespace Controllers;


use Helpers\MessageHelper;
use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Redis;
use Traits\RedisTrait;

class MemberController
{
    use RedisTrait;


    /**
     * @param array $data
     * @return array[]
     */
    public function getChatMembers(array $data)
    {
        $chatId = $data['chat_id'];

        $membersId = $this->redis->zRangeByScore("chat:members:{$chatId}", 0, 3, ['withscores' => true]);
        $responseData = [];

        foreach ($membersId as $memberId => $role) {
            $online = UserHelper::checkOnline($memberId, $this->redis);
            $memberId = preg_replace('#\s#','',$memberId);

            $responseData[] = [
                'user_id' => $memberId,
                'chat_id' => $chatId,
                'avatar' => $this->redis->get("user:avatar:$memberId"),
                'avatar_url' => MessageHelper::AVATAR_URL,
                'user_name' => $this->redis->get("user:name:$memberId"),
                'online' => $online,
                'role' => strval($role),
                'email' => $this->redis->get("user:email:{$memberId}") ?? '',
                'phone' => $this->redis->get("user:phone:{$memberId}") ?? '',
            ];
        }
        $this->redis->close();
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseData);


    }

    /**
     * @param array $data
     * @return array[]
     */
    public function changeUserPrivileges(array $data)
    {
        $chatId = $data['chat_id'];
        $userId = $data['user_id'];
        $role = $data['role'];
        dump("work change");
        $membersForChange = explode(',', $data['members']);
        $chatMembers = $this->redis->zRangeByScore("chat:members:{$chatId}", ChatController::OWNER, 3);

        $checkAdmin = array_search($userId, $chatMembers);
        $checkMembersForChange = array_intersect($membersForChange, $chatMembers);
        dump($checkMembersForChange);
        $changeUsers = [];
        dump($checkAdmin );
        //@TODO подключить middleware
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

        return ResponseFormatHelper::successResponseInCorrectFormat($chatMembers, [
            'status' => 'false',
            'message' => 'недостаточно прав для изменения привилегий',
        ]);

    }

    public function deleteMembers(array $data)
    {
        $chatId = $data['chat_id'];
        $userId = $data['user_id'];

        $deletedMembers = explode(',',$data['members']);

        $chatMembers = $this->redis->zRangeByScore("chat:members:{$chatId}", ChatController::OWNER, '+inf', ['withscores' => true]);

        foreach ($deletedMembers as $deletedMember) {
            if (in_array($deletedMember,$chatMembers)) {
                $this->redis->zRem("chat:members:{$chatId}",$deletedMember);
                $this->redis->zRem("user:chat:{$deletedMember}",$chatId);

                $this->redis->zAdd("chat:members:{$chatId}",['NX'],ChatController::BANNED,$deletedMember);
            }
        }

    }


    /**
     * @param array $data
     * @return array[]
     */
    public function addMembers(array $data): array
    {
        $chatId = $data['chat_id'];
        $membersId = array_unique(explode(',', $data['members_id']));
        $userId = $data['user_id'];

        $chatMembers = $this->redis->zRangeByScore("chat:members:{$chatId}", ChatController::OWNER, 3, ['withscores' => true]);
        $userRole = (int)$chatMembers[$userId] ?? 'not allowed';

        // @TODO вынести в отдельный middleware
        $checkAdmin = in_array($userRole, ChatController::getRolesForAdministrators());

        if ($checkAdmin) {
            foreach ($membersId as $memberId) {
                if (!array_key_exists($memberId, $chatMembers)) {
                    $this->redis->zAdd("user:chats:{$memberId}", ['NX'], time(), $memberId);
                    $this->redis->zAdd("chat:members:{$chatId}", ['NX'], ChatController::SUBSCRIBER, $memberId);

                    // тут создается стандартное сообщение о том что пользователь добавлен в группу
                    $memberName = $this->redis->get("user:name:{$memberId}");

                    //@TODO вынести в хелпер
                    $this->redis->hSet("user:add:chat:{$memberId}:{$chatId}", 'user_id', 13);
                    $this->redis->hSet("user:add:chat:{$memberId}:{$chatId}", 'chat_id', $chatId);
                    $this->redis->hSet("user:add:chat:{$memberId}:{$chatId}", 'status', 1);
                    $this->redis->hSet("user:add:chat:{$memberId}:{$chatId}", 'text', "Пользователь $memberName добавлен в групповой чат");
                    $this->redis->hSet("user:add:chat:{$memberId}:{$chatId}", 'time', time());
                    $this->redis->hSet("user:add:chat:{$memberId}:{$chatId}", 'type', MessageHelper::SYSTEM_MESSAGE_TYPE);

                    // тут оно добавляется в список всех сообщений чата
                    $this->redis->zAdd("chat:{$chatId}", ['NX'], time(), "user:add:chat:{$memberId}:{$chatId}");

                }
            }
            return ResponseFormatHelper::successResponseInCorrectFormat([$userId], [
                'status' => 'true'
            ]);
        }

        return ResponseFormatHelper::successResponseInCorrectFormat([$userId], [
            'status' => 'false',
            'message' => 'только администратор или владелц могут добавлять участников'
        ]);

    }


    /**
     * @param array $data
     * @return array[]
     */
    public function checkExists(array $data)
    {
        $memberId = $data['member_id'];
        $chatId = $data['chat_id'];
        $userId = $data['user_id'];

        $allMembers = $this->redis->zRange("chat:members:{$chatId}", 0, -1);

        if (in_array($memberId, $allMembers)) {
            $this->redis->close();
            return ResponseFormatHelper::successResponseInCorrectFormat([$userId], [
                'status' => 'true',
            ]);

        }
        $this->redis->close();
        return ResponseFormatHelper::successResponseInCorrectFormat([$userId], [
            'status' => 'false',
        ]);


    }


}