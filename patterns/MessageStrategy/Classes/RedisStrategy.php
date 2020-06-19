<?php

namespace Patterns\MessageStrategy\Classes;


use Controllers\MessageController;
use Helpers\MessageHelper;
use Helpers\ResponseFormatHelper;
use Patterns\MessageFactory\Factory;
use Patterns\MessageStrategy\Interfaces\MessageStrategyInterface;
use Redis;

class RedisStrategy implements MessageStrategyInterface
{

    private Redis $redis;

    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }


    public function getMessages(array $data): array
    {
        $count = $data['count'];
        $page = $data['page'];
        $chatId = $data['chat_id'];

        $startChat = $count * $page - $count;
        $endChat = $startChat + $count;

        $chatMessagesId = $this->redis->zRevRangeByScore("chat:{$chatId}", '+inf', '-inf', ['limit' => [$startChat, $endChat, 'withscores' => true]]);
        $messagesForDivider = [];
        foreach ($chatMessagesId as $chatMessageId) {
            $chatStartTime = $this->redis->zRange("chat:{$chatId}", 0, 0, true);
            $chatStartTime = (int)array_shift($chatStartTime);
            // делаю сообщения прочитанными
            $messageOwner = $this->redis->hGet($chatMessageId, 'user_id');
            $messageWriteStatus = $this->redis->hGet($chatMessageId, 'status');


            if ($messageOwner != $data['user_id'] && $messageWriteStatus != MessageController::WRITE && $messageOwner) {
                $this->redis->incrBy("chat:unwrite:count:{$chatId}", -1);
                $this->redis->hMSet($chatMessageId, ['status' => MessageController::WRITE]);

            }

            $message = $this->redis->hGetAll($chatMessageId);
            $attachments = $message['attachments'] ?? null;

            $checkSelfDeleted = $this->redis->get("self:deleted:{$chatMessageId}");
            $checkAllDeleted = $this->redis->get("all:deleted:{$chatMessageId}");
            //@TODO отрекфакторить
            if ($message && !$checkSelfDeleted && !$checkAllDeleted) {
                $messageType = $message->type ?? 0;
                $messagesForDivider[] = [
                    'id' => $chatMessageId,
                    'user_id' => $message['user_id'],
                    'avatar' => $this->redis->get("user:avatar:{$message['user_id']}"),
                    'avatar_url' => MessageHelper::AVATAR_URL,
                    'user_name' => $this->redis->get("user:name:{$message['user_id']}"),
                    'text' => $message['text'] ?? null,
                    'type' => $message['type'] ?? 0,
                    'chat_id' => $message['chat_id'],
                    'time' => $message['time'],
                    'attachments' => $attachments,
                    'day' => date('d-m-Y', $message['time'] ?? 1),
                    'hour' => date('H:i', $message['time'] ?? 1),
                    'reply_data' => Factory::getItem($messageType)->getOriginalDataForReply($message['reply_message_id'],$this->redis) ?? null
                ];
            }


        }
        $allDays = collect($messagesForDivider)->pluck('day')->unique()->toArray();

        $messagesWithDivider = [];
        foreach ($allDays as $day) {
            $messagesWithDivider[$day] = collect($messagesForDivider)->where('day', $day)->toArray();
        }

        $responseDataWithDivider = [];

        foreach ($messagesWithDivider as $date => $dividerData) {

            $dividerData = array_combine(range(1, count($dividerData)), $dividerData);
            $dividerData[0] = [
                'text' => $date,
                'type' => MessageHelper::SYSTEM_MESSAGE_DIVIDER_TYPE,
            ];
            sort($dividerData);
            $responseDataWithDivider = [...$responseDataWithDivider,...$dividerData];

        }
        $this->redis->close();
        return $responseDataWithDivider;

    }

    public function setCount(?int $count): int
    {
        // TODO: Implement setCount() method.
    }

    public function setPage(?int $page): int
    {
        // TODO: Implement setPage() method.
    }
}