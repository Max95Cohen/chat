<?php

namespace Patterns\MessageStrategy\Classes;


use Carbon\Carbon;
use Controllers\MessageController;
use Helpers\ChatHelper;
use Helpers\MessageHelper;
use Illuminate\Database\Capsule\Manager;
use Patterns\MessageFactory\Factory;
use Patterns\MessageStrategy\Interfaces\MessageStrategyInterface;
use Traits\RedisTrait;

class RedisStrategy implements MessageStrategyInterface
{

    use RedisTrait;


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

            if ($messageOwner != $data['user_id'] && $messageWriteStatus != MessageController::WRITE) {

                $this->redis->watch("chat:unwrite:count:{$chatId}");
                $unwriteCount = intval($this->redis->get("chat:unwrite:count:{$chatId}"));
                if ($unwriteCount > 0) {
                    $unwriteCount-=1;
                    $this->redis->multi();
                    $this->redis->set("chat:unwrite:count:{$chatId}",$unwriteCount);

                    $this->redis->hSet($chatMessageId,'status', MessageController::WRITE);
                }
                $this->redis->exec();
            }

            $message = $this->redis->hGetAll($chatMessageId);
            $attachments = $message['attachments'] ?? null;

            $checkSelfDeleted = $this->redis->get("self:deleted:{$chatMessageId}");
            $checkAllDeleted = $this->redis->get("all:deleted:{$chatMessageId}");


            //@TODO отрекфакторить в redis
            if ($message && !$checkSelfDeleted && !$checkAllDeleted) {
                $messageType = $message['type'] ?? 0;
                $replyMessageId = $message['reply_message_id'] ?? null;

                $messageClass = Factory::getItem($messageType);
                $edit = $message['edit'] ?? 0;
                if ($replyMessageId) {
                    $replyMessageType = $this->redis->hGet($replyMessageId,'type') ?? Manager::table('messages')
                            ->where('redis_id',$replyMessageId)
                            ->orWhere('id',$replyMessageId)
                            ->value('type');
                    $replyMessageClass = Factory::getItem($replyMessageType);
                }

                $forwardMessageId = $message['forward_message_id'] ?? null;
                $forwardData = null;

                if ($forwardMessageId) {

                    $forwardMessage = $this->redis->hGetAll($forwardMessageId) ?? Manager::table("messages")->where('id',$forwardMessageId)->first()->toArray();
                    $avatar = $this->redis->get("user_avatar:{$forwardMessage['user_id']}");
                    $forwardData = [
                        'user_id' => $forwardMessage['user_id'],
                        'avatar' => $avatar == false ? 'noAvatar.png' : $avatar,
                        'chat_id' => $forwardMessage['chat_id'],
                        'chat_name' => $this->redis->get("user:name:{$forwardMessage['user_id']}")
                    ];
                }

                $messageAnotherUserId = $message['another_user_id'] ?? null;

                $messagesForDivider[] = [
                    'id' => $chatMessageId,
                    'user_id' => $message['user_id'],
                    'avatar' => $this->redis->get("user:avatar:{$message['user_id']}"),
                    'phone' =>  $this->redis->get("user:phone:{$message['user_id']}"),
                    'avatar_url' => MessageHelper::AVATAR_URL,
                    'user_name' => $this->redis->get("user:name:{$message['user_id']}"),
                    'text' => $message['text'] ?? null,
                    'type' => $message['type'] ?? 0,
                    'chat_id' => $message['chat_id'],
                    'time' => $message['time'] ?? $chatStartTime,
                    'attachments' => $attachments,
                    'attachment_url' => method_exists($messageClass,'getMediaUrl') ? $messageClass::getMediaUrl() : null,
                    'reply_data' => $replyMessageId ? $replyMessageClass->getOriginalDataForReply($replyMessageId, $this->redis) : null,
                    'forward_data' => $forwardData ? json_encode($forwardData) : null,
                    'write' => $message['status'],
                    'day' => date('d-m-Y', (int)$message['time']),
                    'edit' => $edit,
                    'another_user_id' => $messageAnotherUserId,
                    'another_user_avatar' => $messageAnotherUserId ? $this->redis->get("user:avatar:{$messageAnotherUserId}") : null,
                    'another_user_name' => $messageAnotherUserId ? $this->redis->get("user:name:{$messageAnotherUserId}") : null,
                ];
            }


        }
//        $allDays = collect($messagesForDivider)->pluck('day')->unique()->toArray();
//
//        $messagesWithDivider = [];
//        foreach ($allDays as $day) {
//            $messagesWithDivider[$day] = collect($messagesForDivider)->where('day', $day)->toArray();
//        }
//
//        $responseDataWithDivider = [];
//
//        foreach ($messagesWithDivider as $date => $dividerData) {
//            $dividerData = array_combine(range(1, count($dividerData)), $dividerData);
//            $dividerData[0] = [
//                'text' => $date,
//                'type' => MessageHelper::SYSTEM_MESSAGE_DIVIDER_TYPE,
//                'time' => Carbon::parse($date)->timestamp
//            ];
//            sort($dividerData);
//            $responseDataWithDivider = [...$responseDataWithDivider, ...$dividerData];
//
//        }
        $this->redis->close();
//        dump(collect($messagesForDivider)->sortByDesc('time')->toArray());
        return $messagesForDivider;

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