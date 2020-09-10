<?php


namespace Helpers;

use Illuminate\Database\Capsule\Manager;
use Patterns\MessageFactory\Factory;
use Redis;
use Helpers\MessageHelper;

class ForwardHelper
{

    public static function getForwardFields(array $fields, string $messageId, Redis $redis)
    {

        $text = $fields['text'] ?? null;
        $messageClass = Factory::getItem($fields['type']);
        $replyMessageId = $message['reply_message_id'] ?? null;

        if ($replyMessageId) {

            $replyMessageType = $redis->hGet($replyMessageId, 'type') ?? Manager::table('messages')
                    ->where('redis_id', $replyMessageId)
                    ->orWhere('id', $replyMessageId)
                    ->value('type');


            $replyMessageClass = Factory::getItem($replyMessageType);
        }

//        $forwardMessageId = $message['forward_message_id'] ?? null;

//        if ($forwardMessageId) {
//            $forwardMessage = $redis->hGetAll($forwardMessageId) ?? Manager::table("messages")->where('id', $forwardMessageId)->first()->toArray();
//
//            $forwardData = [
//                'user_id' => $forwardMessage['user_id'],
//                'avatar' => $redis->get("user_avatar:{$forwardMessage['user_id']}"),
//                'chat_id' => $forwardMessage['chat_id'],
//                'chat_name' => $redis->get("user:name:{$forwardMessage['user_id']}"),
//                'user_name' =>$redis->get("user:name:{$forwardMessage['user_id']}"),
//            ];
//            $message->text = $forwardMessage['text'] ?? null;
//        }

        $edit = $fields['edit'] ?? null;
        return [
            'message_id' => $messageId,
            'user_id' => $fields['user_id'],
            'avatar' => $redis->get("user:avatar:{$fields['user_id']}"),
            'phone' => $redis->get("userId:phone:{$fields['user_id']}"),
            'avatar_url' => MessageHelper::AVATAR_URL,
            'user_name' => $redis->get("user:name:{$fields['user_id']}"),
            'text' => $text,
            'type' => $fields['type'],
            'chat_id' => $fields['chat_id'],
            'time' => $fields['time'],
            'attachments' => null,
            'attachment_url' => method_exists($messageClass, 'getMediaUrl') ? $messageClass::getMediaUrl() : null,
            'reply_data' => $replyMessageId ? $replyMessageClass->getOriginalDataForReply($replyMessageId, $redis) : null,
            'forward_data' => null,
            'write' => $fields['status'],
            'edit' => $edit,
            'chat_name' => ChatHelper::getChatName($fields['chat_id'],$fields['user_id'],$redis),
        ];
    }


}