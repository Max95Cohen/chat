<?php


namespace Controllers;


use Helpers\ChatHelper;
use Helpers\MessageHelper;
use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Illuminate\Database\Capsule\Manager;
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

        $membersId = $this->redis->zRangeByScore("chat:members:{$chatId}", ChatController::OWNER, '+inf', ['withscores' => true]);
        $responseData = [];

        foreach ($membersId as $memberId => $role) {
            $online = UserHelper::checkOnline($memberId, $this->redis);
            $memberId = preg_replace('#\s#', '', $memberId);

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
        dump($checkAdmin);
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
        $bannedMemberId = $data['member_id'];

        $this->redis->zRem("chat:members:{$chatId}", $bannedMemberId);
        $this->redis->zRem("user:chat:{$bannedMemberId}", $chatId);

        $this->redis->zAdd("chat:members:{$chatId}", ['NX'], ChatController::BANNED, $bannedMemberId);


        $notifyUsers = ChatHelper::getChatMembers($chatId, $this->redis);
        $bannedTime = time();
        Manager::table('chat_members')
            ->where('chat_id', $data['chat_id'])
            ->where('user_id', $bannedMemberId)
            ->update([
                'role' => ChatController::BANNED,
                'banned_time' => $bannedTime,
            ]);
        Manager::table('chats')->where('id', $chatId)->decrement('members_count', 1);


        $adminName = $this->redis->get("user:name:{$data['user_id']}");
        $deletedMemberName = $this->redis->get("user:name:{$data['member_id']}");


        //создание сообщения об удалении пользователя
        $delMessageRedisKey = "user:delete:chat:{$chatId}:{$bannedMemberId}";
        $this->redis->hSet($delMessageRedisKey, 'user_id', 13);
        $this->redis->hSet($delMessageRedisKey, 'text', "$adminName выгнал(а) $deletedMemberName из группы");
        $this->redis->hSet($delMessageRedisKey, 'chat_id', $chatId);
        $this->redis->hSet($delMessageRedisKey, 'status', 1);
        $this->redis->hSet($delMessageRedisKey, 'time', $bannedTime - 1);
        $this->redis->hSet($delMessageRedisKey, 'type', MessageHelper::SYSTEM_MESSAGE_TYPE);

        $this->redis->zAdd("chat:{$chatId}", ['NX'], $bannedTime - 1, $delMessageRedisKey);

        $this->redis->zAdd('all:messages', ['NX'], $bannedTime, $delMessageRedisKey);
        $this->redis->set("user:delete:in:chat:{$bannedMemberId}:{$chatId}", $bannedTime);

        return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers, [
            'status' => 'true',
            'chat_id' => $chatId,
            'user_id' => $bannedMemberId
        ]);

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
            $insertDataMysql = [];
            foreach ($membersId as $memberId) {
                if (!array_key_exists($memberId, $chatMembers)) {
                    $this->redis->zAdd("user:chats:{$memberId}", ['NX'], time(), $data['chat_id']);
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

                    $this->redis->zAdd('all:messages', ['NX'], ChatController::SUBSCRIBER, "user:add:chat:{$memberId}:{$chatId}");
                    // тут оно добавляется в список всех сообщений чата
                    $this->redis->zAdd("chat:{$chatId}", ['NX'], time(), "user:add:chat:{$memberId}:{$chatId}");

                    $insertDataMysql[] = [
                        'user_id' => $memberId,
                        'chat_id' => $data['chat_id'],
                        'role' => ChatController::SUBSCRIBER,
                    ];

                }
            }
            Manager::table('chats')->where('id', $data['chat_id'])->increment('members_count', count($insertDataMysql));

            Manager::table('chat_members')->updateOrInsert($insertDataMysql);
            $this->redis->close();
            return ResponseFormatHelper::successResponseInCorrectFormat([$userId], [
                'status' => 'true',
                'chat_id' => $data['chat_id'],
                'members_count' => $this->redis->zCount("chat:members:{$chatId}", ChatController::OWNER, '+inf'),
            ]);
        }
        $this->redis->close();
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
    //@TODO отрефакторить и вынести в хелпер создание сообщения и поиск подпискичка тоже
    public function chatLeave(array $data)
    {
        $this->redis->zRem("chat:members:{$data['chat_id']}",$data['user_id']);
        $bannedTime = time();
        Manager::table("chat_members")
            ->where('user_id',$data['user_id'])
            ->where('chat_id',$data['chat_id'])
            ->delete();

        Manager::table('chats')
            ->where('id',$data['chat_id'])
            ->decrement('members_count',1);

        // удаляю чат из списка чатов пользователя

        $this->redis->zRem("user:chats:{$data['user_id']}",$data['chat_id']);


        $deletedMemberName = $this->redis->get("user:name:{$data['user_id']}");

        $delMessageRedisKey = "user:leave:chat:{$data['chat_id']}:{$data['user_id']}";
        $this->redis->hSet($delMessageRedisKey, 'user_id', 13);
        $this->redis->hSet($delMessageRedisKey, 'text', " $deletedMemberName вышел из группы");
        $this->redis->hSet($delMessageRedisKey, 'chat_id', $data['chat_id']);
        $this->redis->hSet($delMessageRedisKey, 'status', MessageHelper::MESSAGE_NO_WRITE_STATUS);
        $this->redis->hSet($delMessageRedisKey, 'time', $bannedTime - 1);
        $this->redis->hSet($delMessageRedisKey, 'type', MessageHelper::SYSTEM_MESSAGE_TYPE);

        $this->redis->zAdd('all:messages', ['NX'], ChatController::SUBSCRIBER, $delMessageRedisKey);
        // тут оно добавляется в список всех сообщений чата
        $this->redis->zAdd("chat:{$data['chat_id']}", ['NX'], time(), $delMessageRedisKey);

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],[
            'status' => 'true',
            'user_id' => $data['user_id'],
            'chat_id' => $data['chat_id'],
            'members_count' => $this->redis->zCount("chat:members:{$data['chat_id']}", ChatController::OWNER, '+inf'),
        ]);

    }


}