<?php

namespace Patterns\ChatFactory\Classes;

use Controllers\ChatController;
use Helpers\ResponseFormatHelper;
use Illuminate\Database\Capsule\Manager as DB;
use Patterns\ChatFactory\Interfaces\BaseChatCreateInterface;
use Redis;
use Traits\RedisTrait;

class PrivateChat implements BaseChatCreateInterface
{
    use RedisTrait;

    /**
     * @param array $data
     * @param Redis $redis
     * @return mixed
     */
    public function create(array $data, Redis $redis)
    {
        $anotherUserId = $data['user_ids'];
        $checkChat = $redis->get("private:{$anotherUserId}:{$data['user_id']}");
        $anotherUserAvatar = $redis->get("user:avatar:{$anotherUserId}") ?? '';
        $anotherUserName = $redis->get("user:name:{$anotherUserId}");

        // если чат между двумя пользователями уже существует вовзращаю его

        if ($checkChat) {
            $chatDeleted = $this->redis->get("chat:deleted:{$data['user_id']}:{$checkChat}");

            if (!$chatDeleted) {
                return [
                    'status' => 'false',
                    'chat_id' => $checkChat,
                    'name' => $anotherUserName,
                    'avatar' => $anotherUserAvatar,
                    'user_id' => $anotherUserId,
                ];
            }
        }

        $twoUsers = array_merge([$data['user_id']], [$data['user_ids']]);

        // добавляю созданный чат в mysql
        $chatId = DB::table('chats')->insertGetId([
            'owner_id' => $data['user_id'],
            'name' => $data['chat_name'],
            'type' => $data['type'],
            'members_count' => 2,
        ]);

        $membersData = [];

        foreach ($twoUsers as $userId) {
            $membersData[] = [
                'user_id' => $userId,
                'chat_id' => $chatId,
                'role' => ChatController::OWNER,
            ];

            $this->redis->zAdd("chat:members:{$chatId}", ['NX'], ChatController::OWNER, $userId);
            $this->redis->zAdd("user:chats:{$userId}", ['NX'], time(), $chatId);
            $this->redis->set("private:{$userId}:{$data['user_id']}", $chatId);
            $this->redis->set("private:{$data['user_id']}:{$userId}", $chatId);
        }

        DB::table('chat_members')->insert($membersData);

        $this->redis->zAdd("chat:{$chatId}", ['NX'], time(), "chat:message:create");

        $this->redis->close();

        return [
            'status' => 'true',
            'chat_name' => $anotherUserName,
            'chat_id' => $chatId,
            'user_id' => $anotherUserId,
            'type' => $data['type'],
        ];
    }
}
