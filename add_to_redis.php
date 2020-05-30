<?php



$redis->hSet("message:$userId:$messageId", 'user_id', $data['user_id']);
$redis->hSet("message:$userId:$messageId", 'text', $data['text']);
$redis->hSet("message:$userId:$messageId", 'chat_id', $data['chat_id']);
$redis->hSet("message:$userId:$messageId", 'status', self::NO_WRITE);
$redis->hSet("message:$userId:$messageId", 'time', $messageTime);