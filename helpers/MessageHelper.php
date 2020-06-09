<?php


namespace Helpers;
use Redis;

class MessageHelper
{
    public static function getMessageIncorrectFormat($allMessages,Redis $redis)
    {
        $responseData = [];
        foreach ($allMessages as $messageId => $message) {
            $avatar = $redis->get("user:avatar:{$message['user_id']}");
            $name = $redis->get("user:name:{$message['user_id']}");
            $responseData[] = [
                'id' => $message['id'],
                'user_id' => $message['user_id'],
                'user_name' => $name,
                'avatar' => $avatar,
                'avatar_url' =>'https://media.indigo24.com/avatars/',
                'text' => $message['text'],
                'time' => $message['time'],
                'write' => $message['write'] ?? '0',
            ];
        }
        return $responseData;
    }

    public static function deleteExtraSpaces(string $text)
    {
        return preg_replace('#\s{2,}#',' ',$text);
    }



}