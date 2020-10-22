<?php

namespace Patterns\MessageStrategy\Classes;


use Carbon\Carbon;
use Controllers\MessageController;
use Helpers\ChatHelper;
use Helpers\ForwardHelper;
use Helpers\Helper;
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

//        Helper::log($chatMessagesId); # TODO remove;

        $messagesForDivider = [];
        foreach ($chatMessagesId as $chatMessageId) {
            $chatStartTime = $this->redis->zRange("chat:{$chatId}", 0, 0, true);
            $chatStartTime = (int)array_shift($chatStartTime);
            // делаю сообщения прочитанными
            $messageOwner = $this->redis->hGet($chatMessageId, 'user_id');
            $messageWriteStatus = $this->redis->hGet($chatMessageId, 'status');

            if ($messageOwner != $data['user_id'] && $messageWriteStatus != MessageController::WRITE) {
                $this->redis->hSet($chatMessageId, 'status', MessageController::WRITE);
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
                    $replyMessageType = $this->redis->hGet($replyMessageId, 'type') ?? Manager::table('messages')
                            ->where('redis_id', $replyMessageId)
                            ->orWhere('id', $replyMessageId)
                            ->value('type');
                    $replyMessageClass = Factory::getItem($replyMessageType);
                }

                $forwardMessageId = $message['forward_message_id'] ?? null;
                $forwardData = null;

                if ($forwardMessageId) {
                    $forwardMessage = $this->redis->hGetAll($forwardMessageId);
                    $forwardMessageInMysql = Manager::table("messages")->where('id', $forwardMessageId)->first();

                    if (!$forwardMessageInMysql && !$forwardMessage) {
                        continue;
                    }
                    $forwardMessage = $forwardMessage == [] ? $forwardMessageInMysql->toArray() : $forwardMessage;

                    $avatar = $this->redis->get("user_avatar:{$forwardMessage['user_id']}");
                    $forwardText = $forwardMessage['text'] ?? null;

                    $forwardData = ForwardHelper::getForwardFields($forwardMessage, $forwardMessageId, $this->redis);
                    $message['text'] = $forwardText;
                }

                if ($messageType == MessageHelper::STICKER_MESSAGE_TYPE && $attachments) {
                    $attachments = json_decode($attachments, true);
                    $stickerId = $attachments[0]['stick_id'];

                    $sticker = $this->redis->hGetAll("sticker:{$stickerId}");

                    $attachments = json_encode([[
                        'stick_id' => $stickerId,
                        'path' => $sticker['path']
                    ]], JSON_UNESCAPED_UNICODE);
                }

                $attachmentsNew = json_decode($attachments, true);

                $messageAnotherUserId = $message['another_user_id'] ?? null;
                $extension = $message['extension'] ?? null;
                $messageTime = $message['time'] ?? $chatStartTime;
                $checkTime = $message['time'] ?? null;

                //@TODO temporary fix

                if (!$checkTime) {
                    continue;
                }

                $text = $message['text'] ?? 'null';

                if (empty($text)) {
                    $text = 'null';
                }

                $type = $message['type'] ?? 0;

                $messagesForDivider[] = [
                    'id' => $chatMessageId,
                    'user_id' => $message['user_id'],
                    'avatar' => $this->redis->get("user:avatar:{$message['user_id']}"),
                    'phone' => $this->redis->get("user:phone:{$message['user_id']}"),
                    'avatar_url' => MessageHelper::AVATAR_URL,
                    'user_name' => $this->redis->get("user:name:{$message['user_id']}"),
                    'text' => $text,
                    'type' => $type,
                    'chat_id' => $message['chat_id'] ?? null,
                    'extension' => $extension == false ? null : $extension,
                    'time' => $messageTime,
                    'attachments' => $attachments,
                    'attachmentsNew' => $attachmentsNew,
                    'attachment_url' => method_exists($messageClass, 'getMediaUrl') ? $messageClass::getMediaUrl() : null,
                    'reply_data' => $replyMessageId ? $replyMessageClass->getOriginalDataForReply($replyMessageId, $this->redis) : null,
                    'forward_data' => $forwardData ? json_encode($forwardData) : null,
                    'forward_dataNew' => $forwardData ?: null,
                    'write' => $message['status'],
                    'day' => date('d-m-Y', (int)$message['time']),
                    'edit' => $edit,
                    'another_user_id' => $messageAnotherUserId,
                    'another_user_avatar' => $messageAnotherUserId ? $this->redis->get("user:avatar:{$messageAnotherUserId}") : null,
                    'another_user_name' => $messageAnotherUserId ? $this->redis->get("user:name:{$messageAnotherUserId}") : null,
                    'online_users_count' => ChatHelper::getOnlineUsersCount((int)$chatId, $this->redis),
                    'message_for_type' => MessageHelper::getAttachmentTypeString($message['type'])
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