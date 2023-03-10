<?php


namespace Controllers;


use Helpers\ChatHelper;
use Helpers\MessageHelper;
use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Str;
use Redis;
use Traits\RedisTrait;

class MemberController
{
    use RedisTrait;

    const BOT_ID = 13;

    /**
     * @param array $data
     * @return array[]
     */
    public function getChatMembers(array $data)
    {
        $chatId = $data['chat_id'];
        $page = $data['page'] ?? 1;
        $onePageChatCount = 20;

        $startChat = $onePageChatCount * $page - $onePageChatCount;
        $endChat = $startChat + $onePageChatCount;

        $membersId = $this->redis->zRevRangeByScore("chat:members:{$chatId}", "+inf", 0, ['limit' => [$startChat, $endChat], 'withscores' => true]);
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
                'role' => strval(intval($role)),
                'email' => $this->redis->get("user:email:{$memberId}") ?? '',
                'phone' => $this->redis->get("userId:phone:{$memberId}") ?? '',
            ];
            usort($responseData, function ($a, $b) {
                return $a['role'] <=> $b['role'];
            });

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

        $membersForChange = $data['members'];

        $chatMembers = $this->redis->zRangeByScore("chat:members:{$chatId}", 0, ChatController::OWNER, ['withscores' => true]);

        $checkAdmin = false;

        if (isset($chatMembers[$userId])) {
            $checkAdmin = $chatMembers[$userId];
        }

        $changeUsers = [];

        //@TODO ???????????????????? middleware
        if ($checkAdmin == ChatController::OWNER && in_array($role, ChatController::getRolesForOwner())) {
            foreach ($chatMembers as $memberID => $roleValue) {
                if (in_array($memberID, $membersForChange)) {
                    $this->redis->zAdd("chat:members:{$chatId}", ['CH'], $role, $memberID);

                    $changeUsers[] = [
                        'user_id' => $memberID,
                        'role' => $role,
                    ];
                }
            }

            $chatMembers = array_map(function ($i) {
                return intval($i);
            }, $chatMembers);

            $chatMembers = array_keys($chatMembers);

            return ResponseFormatHelper::successResponseInCorrectFormat($chatMembers, [
                'status' => 'true',
                'users' => $changeUsers,
                'chat_id' => $chatId
            ]);
        }

        return ResponseFormatHelper::successResponseInCorrectFormat($chatMembers, [
            'status' => 'false',
            'message' => '???????????????????????? ???????? ?????? ?????????????????? ????????????????????',
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
        $chatData = Manager::table('chats')->where('id', $chatId)->first();

        $adminName = $this->redis->get("user:name:{$data['user_id']}");
        $deletedMemberName = $this->redis->get("user:name:{$data['member_id']}");


        //???????????????? ?????????????????? ???? ???????????????? ????????????????????????
        $delMessageRedisKey = "user:delete:chat:{$chatId}:{$bannedMemberId}";
        $this->redis->hSet($delMessageRedisKey, 'user_id', self::BOT_ID);
        $this->redis->hSet($delMessageRedisKey, 'text', "$adminName ????????????????(??) $deletedMemberName ???? ????????????");
        $this->redis->hSet($delMessageRedisKey, 'chat_id', $chatId);
        $this->redis->hSet($delMessageRedisKey, 'status', 1);
        $this->redis->hSet($delMessageRedisKey, 'time', $bannedTime - 1);
        $this->redis->hSet($delMessageRedisKey, 'type', MessageHelper::SYSTEM_MESSAGE_TYPE);

        $this->redis->zAdd("chat:{$chatId}", ['NX'], $bannedTime - 1, $delMessageRedisKey);

        $this->redis->zAdd('all:messages', ['NX'], $bannedTime, $delMessageRedisKey);
        $this->redis->set("user:delete:in:chat:{$bannedMemberId}:{$chatId}", $bannedTime);

        $chatMembersCount = $this->redis->zCount("chat:members:{$chatId}", ChatController::SUBSCRIBER, ChatController::OWNER);

        //@TODO ?????? ???????????????? ?????????? ???????????? ???????????? ???????? ???????????? ????????????????
        $multiResponseData = [];
        $multiResponseData['responses'][0]['cmd'] = 'message:create';
        $multiResponseData['responses'][0]['notify_users'] = ChatHelper::getChatMembers($chatId, $this->redis);
        $multiResponseData['responses'][0]['data'] = [
            "message_id" => $delMessageRedisKey,
            "status" => true,
            "mute" => ChatController::CHAT_MUTE,
            "write" => 1,
            "chat_id" => $data['chat_id'],
            "user_id" => self::BOT_ID,
            "time" => $bannedTime - 1,
            "avatar" => UserHelper::getUserAvatar(self::BOT_ID, $this->redis),
            "avatar_url" => MessageHelper::AVATAR_URL,
            "user_name" => '',
            "chat_name" => $chatData->name,
            "chat_type" => $chatData->type,
            "text" => "$adminName ????????????????(??) $deletedMemberName ???? ????????????",
            "type" => MessageHelper::SYSTEM_MESSAGE_TYPE,
        ];


        $multiResponseData['responses'][1]['cmd'] = 'chat:members:delete';
        $multiResponseData['responses'][1]['notify_users'] = [$data['user_id']];
        $multiResponseData['responses'][1]['data'] = [
            'status' => 'true',
            'chat_id' => $chatId,
            'user_id' => $bannedMemberId,
            'member_count' => $chatMembersCount
        ];

        $multiResponseData['multi_response'] = true;

        return $multiResponseData;
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

        $chatMembers = $this->redis->zRangeByScore("chat:members:{$chatId}", 0, ChatController::OWNER, ['withscores' => true]);
        $userRole = (int)$chatMembers[$userId] ?? 'not allowed';

        // @TODO ?????????????? ?? ?????????????????? middleware
        $checkAdmin = in_array($userRole, ChatController::getRolesForAdministrators());

        if ($checkAdmin) {
            $insertDataMysql = [];
            foreach ($membersId as $memberId) {
                if (!array_key_exists($memberId, $chatMembers)) {
                    $this->redis->zAdd("user:chats:{$memberId}", ['NX'], time(), $data['chat_id']);
                    $this->redis->zRem("chat:members:{$chatId}", $memberId);
                    $this->redis->zAdd("chat:members:{$chatId}", ['NX'], ChatController::SUBSCRIBER, $memberId);

                    // ?????? ?????????????????? ?????????????????????? ?????????????????? ?? ?????? ?????? ???????????????????????? ???????????????? ?? ????????????
                    $memberName = $this->redis->get("user:name:{$memberId}");

                    //@TODO ?????????????? ?? ????????????
                    $this->redis->hSet("user:add:chat:{$memberId}:{$chatId}", 'user_id', 13);
                    $this->redis->hSet("user:add:chat:{$memberId}:{$chatId}", 'chat_id', $chatId);
                    $this->redis->hSet("user:add:chat:{$memberId}:{$chatId}", 'status', 1);
                    $this->redis->hSet("user:add:chat:{$memberId}:{$chatId}", 'text', "???????????????????????? $memberName ???????????????? ?? ?????????????????? ??????");
                    $this->redis->hSet("user:add:chat:{$memberId}:{$chatId}", 'time', time());
                    $this->redis->hSet("user:add:chat:{$memberId}:{$chatId}", 'type', MessageHelper::SYSTEM_MESSAGE_TYPE);

                    $this->redis->zAdd('all:messages', ['NX'], ChatController::SUBSCRIBER, "user:add:chat:{$memberId}:{$chatId}");
                    // ?????? ?????? ?????????????????????? ?? ???????????? ???????? ?????????????????? ????????
                    $this->redis->zAdd("chat:{$chatId}", ['NX'], time(), "user:add:chat:{$memberId}:{$chatId}");

                    $insertDataMysql[] = [
                        'user_id' => $memberId,
                        'chat_id' => $data['chat_id'],
                        'role' => ChatController::SUBSCRIBER,
                    ];

                }
            }
            Manager::table('chats')->where('id', $data['chat_id'])->increment('members_count', count($insertDataMysql));

            Manager::table('chat_members')->insert($insertDataMysql);
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
            'message' => '???????????? ?????????????????????????? ?????? ?????????????? ?????????? ?????????????????? ????????????????????'
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

    //@TODO ?????????????????????????? ?? ?????????????? ?? ???????????? ???????????????? ?????????????????? ?? ?????????? ?????????????????????? ????????
    public function chatLeave(array $data)
    {
        $chatId = $data['chat_id'];

        $this->redis->zRem("chat:members:{$data['chat_id']}", $data['user_id']);
        $bannedTime = time();
        Manager::table("chat_members")
            ->where('user_id', $data['user_id'])
            ->where('chat_id', $data['chat_id'])
            ->delete();

        Manager::table('chats')
            ->where('id', $data['chat_id'])
            ->decrement('members_count', 1);

        // ???????????? ?????? ???? ???????????? ?????????? ????????????????????????

        $this->redis->zRem("user:chats:{$data['user_id']}", $data['chat_id']);


        $deletedMemberName = $this->redis->get("user:name:{$data['user_id']}");

        $delMessageRedisKey = "user:leave:chat:{$data['chat_id']}:{$data['user_id']}";
        $this->redis->hSet($delMessageRedisKey, 'user_id', 13);
        $this->redis->hSet($delMessageRedisKey, 'text', " $deletedMemberName ?????????? ???? ????????????");
        $this->redis->hSet($delMessageRedisKey, 'chat_id', $data['chat_id']);
        $this->redis->hSet($delMessageRedisKey, 'status', MessageHelper::MESSAGE_NO_WRITE_STATUS);
        $this->redis->hSet($delMessageRedisKey, 'time', $bannedTime - 1);
        $this->redis->hSet($delMessageRedisKey, 'type', MessageHelper::SYSTEM_MESSAGE_TYPE);

        $this->redis->zAdd('all:messages', ['NX'], ChatController::SUBSCRIBER, $delMessageRedisKey);
        // ?????? ?????? ?????????????????????? ?? ???????????? ???????? ?????????????????? ????????
        $this->redis->zAdd("chat:{$data['chat_id']}", ['NX'], time(), $delMessageRedisKey);


        // all chat users

        $chatMembers = ChatHelper::getChatMembers($chatId, $this->redis);
        array_push($chatMembers, $data['user_id']);

        $multiResponseData = [];

        $botId = self::BOT_ID;
        $multiResponseData['responses'][0]['cmd'] = 'message:create';
        $multiResponseData['responses'][0]['notify_users'] = $chatMembers;
        $multiResponseData['responses'][0]['data'] = [
            'status' => true,
            'write' => 1,
            'chat_id' => $chatId,
            'message_id' => $delMessageRedisKey,
            'user_id' => $botId,
            'time' => $bannedTime,
            'text' => " $deletedMemberName ?????????? ???? ????????????",
            'avatar' => UserHelper::DEFAULT_AVATAR,
            "avatar_url" => MessageHelper::AVATAR_URL,
            'user_name' => $this->redis->get("user:name:{$botId}"),
            'attachments' => null,
            'forward_message_id' => null,
            'reply_message_id' => null,
            'forward_data' => null,
            'reply_data' => null,
            'type' => MessageHelper::SYSTEM_MESSAGE_TYPE
        ];

        $multiResponseData['multi_response'] = true;
        $multiResponseData['members_count'] = $this->redis->zCount("chat:members:{$data['chat_id']}", ChatController::SUBSCRIBER, '+inf');
        return $multiResponseData;

    }

    /**
     * @param array $data
     * @return array[]
     */
    public function searchInChat(array $data): array
    {
        $chatId = $data['chat_id'];
        $chatMembersId = $this->redis->zRange("chat:members:{$chatId}", 0, -1, true);
        $search = $data['search'];

        $members = [];

        foreach ($chatMembersId as $memberId => $role) {
            $online = UserHelper::checkOnline($memberId, $this->redis);
            $members[] = [
                'user_id' => $memberId,
                'chat_id' => $chatId,
                'avatar' => UserHelper::getUserAvatar($memberId, $this->redis),
                'avatar_url' => MessageHelper::AVATAR_URL,
                'user_name' => $this->redis->get("user:name:$memberId"),
                'online' => $online,
                'role' => strval(intval($role)),
                'email' => $this->redis->get("user:email:{$memberId}") ?? '',
                'phone' => $this->redis->get("user:phone:{$memberId}") ?? '',
            ];
        }

        $searchUsers = collect($members)->filter(function ($member) use ($search) {
            $memberName = mb_strtolower($member['user_name']);
            $search = mb_strtolower($search);
            return
                Str::startsWith($member['phone'], $search) ||
                Str::startsWith($memberName, $search);
        })->values()->all();

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $searchUsers);

    }

    /**
     * @param array $data
     * @return array[]
     */
    public function deleteChat(array $data)
    {
        $userId = $data['user_id'];
        $chatId = $data['chat_id'];

        $userStatus = $this->redis->zRangeByScore("chat:members:{$chatId}", $userId, $userId);

        if ($userStatus != ChatController::BANNED || $userStatus != false) {
            $this->redis->zRem("user:chat:members:{$chatId}", $userId);
        }

        $this->redis->zRem("user:chats:{$userId}", $chatId);
        $this->redis->set("chat:deleted:{$userId}:{$chatId}", time());

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], [
            "chat_id" => $chatId,
            "status" => true
        ]);
    }
}
