<?php


namespace Patterns\ChatFactory\Classes;


use Controllers\ChatController;
use Helpers\ChatHelper;
use Helpers\ResponseFormatHelper;
use Illuminate\Database\Capsule\Manager as DB;
use Patterns\ChatFactory\Factory;
use Patterns\ChatFactory\Interfaces\BaseChatCreateInterface;
use Redis;

class GroupChat implements BaseChatCreateInterface
{

    /**
     * @param array $data
     * @param Redis $redis
     * @return array
     */
    public function create(array $data, Redis $redis)
    {
        $userIds = explode(',', $data['user_ids']);
        array_push($userIds, $data['user_id']);
        $userIds = array_unique($userIds);
        $membersCount = count($userIds);


        $chatId = ChatHelper::createChat($data,$membersCount);
        $membersData = [];

        foreach ($userIds as $userId) {
            $role = $userId == $data['user_id'] ? ChatController::OWNER : ChatController::SUBSCRIBER;
            $membersData[] = [
                'user_id' => $userId,
                'chat_id' => $chatId,
                'role' => $role,
            ];
            $redis->zAdd("chat:members:{$chatId}", ['NX'], $role, $userId);
            $redis->zAdd("user:chats:{$userId}", ['NX'], time(), $chatId);
        }
        DB::table('chat_members')->insert($membersData);
        $redis->zAdd("chat:{$chatId}", ['NX'], time(), "group:message:create");


        return [
            'status' => 'true',
            'chat_name' => $data['chat_name'],
            'members_count' => count($userIds),
            'chat_id' => $chatId,
            'avatar' => "@TODO  add avatar",
            'type' => $data['type'],
        ];
    }
}