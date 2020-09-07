<?php


namespace Patterns\MessageFactory\Classes;


use Helpers\ForwardHelper;
use Helpers\MessageHelper;
use Illuminate\Database\Capsule\Manager;
use Patterns\MessageFactory\Factory;
use Patterns\MessageFactory\Interfaces\MessageInterface;
use Redis;
use Traits\RedisTrait;

class ForwardMessage implements MessageInterface
{

    use RedisTrait;

    /**
     * @param Redis $redis
     * @param string $redisKey
     * @param array $data
     */
    public function addExtraFields(Redis $redis, string $redisKey, array $data): void
    {
        $text = $data['text'];

        if ($text) {
            $redis->hSet($redisKey, 'text', $text);
        }

        $redis->hSet($redisKey, 'type', MessageHelper::FORWARD_MESSAGE_TYPE);
        $redis->hSet($redisKey, 'attachments', json_encode($data['attachments']));
    }

    public function returnResponseDataForCreateMessage(array $data, string $messageRedisKey, Redis $redis): array
    {
        $messageData = MessageHelper::getResponseDataForCreateMessage($data, $messageRedisKey, $redis);

        $messageData['text'] = $redis->hGet($messageRedisKey, 'text');
        $messageData['type'] = MessageHelper::FORWARD_MESSAGE_TYPE;


        $forwardMessagesId = $data['attachments'];
        $attachments = [];
        $messagesIdInMysql = [];

        foreach ($forwardMessagesId as $forwardMessageId) {
            $messageInRedis = $redis->hGetAll($forwardMessageId);

            if ($messageInRedis) {

                $text = $messageInRedis['text'] ?? null;


                //@TODO временые if потом вынесу в нормальнйы метод

                $messageType = $messageInRedis['type'];
                $messageClass = Factory::getItem($messageType);

                $replyMessageId = $message['reply_message_id'] ?? null;

                if ($replyMessageId) {
                    $replyMessageType = $this->redis->hGet($replyMessageId, 'type') ?? Manager::table('messages')
                            ->where('redis_id', $replyMessageId)
                            ->orWhere('id', $replyMessageId)
                            ->value('type');
                    $replyMessageClass = Factory::getItem($replyMessageType);
                }


                if ($messageType == MessageHelper::STICKER_MESSAGE_TYPE && $attachments) {
                    $attachments = json_decode($attachments, true);
                    $stickerId = $attachments['stick_id'];

                    $sticker = $this->redis->hGetAll("sticker:{$stickerId}");

                    $attachments = [
                        'stick_id' => $stickerId,
                        'path' => $sticker['path']
                    ];

                }

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

                    $forwardData = [
                        'user_id' => $forwardMessage['user_id'],
                        'avatar' => $avatar == false ? 'noAvatar.png' : $avatar,
                        'chat_id' => $forwardMessage['chat_id'],
                        'chat_name' => $this->redis->get("user:name:{$forwardMessage['user_id']}"),
                        'user_name' => $this->redis->get("user:name:{$forwardMessage['user_id']}")
                    ];
                    $message['text'] = $forwardText;
                }

                $edit = $messageInRedis['edit'] ?? 0;


                $attachments[] = [
                    'message_id' => $forwardMessageId,
                    'user_id' => $messageInRedis['user_id'],
                    'avatar' => $this->redis->get("user:avatar:{$messageInRedis['user_id']}"),
                    'phone' => $this->redis->get("user:phone:{$messageInRedis['user_id']}"),
                    'avatar_url' => MessageHelper::AVATAR_URL,
                    'user_name' => $this->redis->get("user:name:{$messageInRedis['user_id']}"),
                    'text' => $text,
                    'type' => $messageType,
                    'chat_id' => $messageInRedis['chat_id'],
                    'time' => $messageInRedis['time'],
                    'attachments' => $attachments,
                    'attachment_url' => method_exists($messageClass, 'getMediaUrl') ? $messageClass::getMediaUrl() : null,
                    'reply_data' => $replyMessageId ? $replyMessageClass->getOriginalDataForReply($replyMessageId, $this->redis) : null,
                    'forward_data' => $forwardData ? json_encode($forwardData) : null,
                    'write' => $messageInRedis['status'],
                    'edit' => $edit,
                ];
            } else {
                $messagesIdInMysql[] = $forwardMessageId;
            }



        }

        $messagesInMysql = Manager::table('messages')->whereIn('redis_id',$messagesIdInMysql)->get()->toArray();

        foreach ($messagesInMysql as $message) {
            $message = collect($message)->toArray();

            array_push($attachments,ForwardHelper::getForwardFields($message,$this->redis));

        }

        $messageData['attachments'] = $attachments;

        return $messageData;
    }


}