<?php

namespace Controllers;

use Helpers\ChatHelper;
use Helpers\ForwardHelper;
use Helpers\GetResponseForMessageType;
use Helpers\MediaHelper;
use Helpers\MessageHelper;
use Helpers\ResponseFormatHelper;
use Helpers\UserHelper;
use Illuminate\Database\Capsule\Manager;
use Patterns\MessageFactory\Factory;
use Redis;
use Traits\RedisTrait;
use Validation\MessageWriteValidation;

class MessageController
{

    const NO_DELETED_STATUS = 0;
    const DELETED_STATUS = 1;
    const EDITED_STATUS = 2;

    const WRITE = 1;
    const NO_WRITE = 0;

    use RedisTrait;

    /**
     * @param array $data
     * @return array
     */
    public function create(array $data)
    {
        // у каждого юзера есть counter сообщений
        $userId = $data['user_id'];
        $chatId = $data['chat_id'];

        $this->redis->incrBy("user:message:{$userId}", 1);
        $messageId = $this->redis->get("user:message:{$userId}");

        $data['message_type'] = $data['message_type'] ?? MessageHelper::TEXT_MESSAGE_TYPE;

        $messageRedisKey = "message:$userId:$messageId";

        $data['message_time'] = time();


        // добаляем сообщение в redis
        MessageHelper::create($this->redis, $data, $messageRedisKey);

        // добавляем дополнительные параметры в зависимости от типа через фабрику

        $messageClass = Factory::getItem($data['message_type']);

        // @TODO максимально тупой временный if (выпуск приложение через пару часов) ненавижу фротов
        $fileId = $data['file_id'] ?? null;

        if ($fileId) {
            $data['mime_type'] = Manager::table('user_uploads')->where('id', $fileId)->value('mime_type');
        }

        $messageClass->addExtraFields($this->redis, $messageRedisKey, $data);

        $data['message_type'] = $this->redis->hGet($messageRedisKey, 'type');
        // добавляем сообщение в чат

        MessageHelper::addMessageInChat($this->redis, $chatId, $messageRedisKey);

        // Если количество сообщений в чате больше чем AVAILABLE_COUNT_MESSAGES_IN_REDIS то самое раннее сообщение удаляется

        MessageHelper::cleanFirstMessageInRedis($this->redis, $chatId);


        // добавляем сообщение в общий список сообщений

        $this->redis->zAdd('all:messages', ['NX'], self::NO_WRITE, "message:$userId:$messageId");

        // ставим последнее время для фильтрации чатов в списке пользователя
        $this->redis->zAdd("user:chats:{$userId}", ['NX'], $data['message_time'], $chatId);


        $notifyUsers = $this->redis->zRangeByScore("chat:members:{$data['chat_id']}", 0, 100);

        $chatMembersWithNotAuthor = $notifyUsers;

        unset($chatMembersWithNotAuthor[$userId]);

        foreach ($notifyUsers as $notifyUser) {
            $this->redis->zAdd("user:chats:{$notifyUser}", ['XX'], $data['message_time'], $chatId);
        }

        // здесь добавляю в очередь на отправку уведомлений!!!!@TODO отрефакторить это
        // пуш в новое приложение
        $this->redis->hSet("push:notify:{$userId}:{$messageId}", 'type', PushController::NOTIFY_CREATE_NEW_MESSAGE_IN_CHAT);
        $this->redis->hSet("push:notify:{$userId}:{$messageId}", 'link', "message:$userId:$messageId");

        // пуш в старое приложение
        $this->redis->hSet("push:notify:old:{$userId}:{$messageId}", 'type', PushController::NOTIFY_CREATE_NEW_MESSAGE_IN_CHAT);
        $this->redis->hSet("push:notify:old:{$userId}:{$messageId}", 'link', "message:$userId:$messageId");


        // очередь для пуша старый андроид и новый
        $this->redis->zAdd("all:notify:queue", ['NX'], time(), "push:notify:{$userId}:{$messageId}");
        $this->redis->zAdd("all:notify:old:android:queue", ['NX'], time(), "push:notify:old:{$userId}:{$messageId}");

        $messageClass = Factory::getItem($data['message_type']);

//        $messageClass->addExtraFields($this->redis,$messageRedisKey,$data);

        $responseData = $messageClass->returnResponseDataForCreateMessage($data, $messageRedisKey, $this->redis);

        // увеличиваем у всех кроме автора сообщения количество непрочитанных на 1

        ChatHelper::incrUnWriteCountForMembers($chatId, $this->redis, $chatMembersWithNotAuthor);

        $this->redis->close();

        return [
            'data' => $responseData,
            'notify_users' => $notifyUsers,
        ];

    }

    /**
     * @param array $data
     * @return array[]
     */
    public function write(array $data)
    {
        $messageId = $data['message_id'];
        $chatId = $data['chat_id'];
        $notifyUsers = $this->redis->zRange("chat:members:{$chatId}", 0, -1);

        $messageOwner = $this->redis->hGet($data['message_id'], 'user_id');

        if ($messageOwner != $data['user_id']) {
            $this->redis->hMSet($data['message_id'], ['status' => MessageHelper::MESSAGE_WRITE_STATUS]);
            $this->redis->zAdd("chat:{$chatId}", ['CH'], MessageController::WRITE, "message:$messageOwner:$messageId");
            ChatHelper::nullifyUnWriteCount($chatId, $data['user_id'], $this->redis);

            $this->redis->close();

            return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers, [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'owner_id' => $messageOwner,
                'write' => strval(MessageController::WRITE),
                'status' => true,
            ]);
        }

    }

    public function read(array $data)
    {
        $messageId = $data['message_id'];
        $chatId = $data['chat_id'];
        $notifyUsers = $this->redis->zRange("chat:members:{$chatId}", 0, -1);

        $messageOwner = $this->redis->hGet($data['message_id'], 'user_id');

        if ($messageOwner != $data['user_id']) {
            $this->redis->hMSet($data['message_id'], ['status' => MessageHelper::MESSAGE_WRITE_STATUS]);
            $this->redis->zAdd("chat:{$chatId}", ['CH'], MessageController::WRITE, "message:$messageOwner:$messageId");
            ChatHelper::nullifyUnWriteCount($chatId, $data['user_id'], $this->redis);

            $this->redis->close();

            return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers, [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'owner_id' => $messageOwner,
                'write' => strval(MessageController::WRITE),
                'status' => true,
            ]);
        }

    }

    /**
     * @param array $data
     * @return array
     */
    public function edit(array $data): array
    {
        $data = Factory::getItem($data['message_type'])->editMessage($data, $this->redis);
        $notifyUsers = ChatHelper::getChatMembers((int)$data['chat_id'], $this->redis);
        $this->redis->close();

        return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers, $data);
    }

    /**
     * @param array $data
     * @return array
     */
    public function delete(array $data): array
    {
        $checkRedis = $this->redis->hGet($data['message_id'], 'type');

        $messageType = $checkRedis === false ? Manager::table('messages')->where('id', $data['message_id'])->value('type') : $checkRedis;

        $data = Factory::getItem($messageType)->deleteMessage($data, $this->redis);

        $this->redis->set("all:delete:{$data['message_id']}", 1);

        $notifyUsers = ChatHelper::getChatMembers((int)$data['chat_id'], $this->redis);

        ChatHelper::incrUnWriteCountForMembers($data['chat_id'], $this->redis, $notifyUsers, -1);

        $this->redis->close();

        return ResponseFormatHelper::successResponseInCorrectFormat($notifyUsers, $data);

    }

    /**
     * @param array $data
     * @return array
     */
    public function deleteSelf(array $data): array
    {
        $checkRedis = $this->redis->hGet($data['message_id'], 'type');

        $messageType = $checkRedis === false ? Manager::table('messages')->where('id', $data['message_id'])->value('type') : $checkRedis;

        $data = Factory::getItem($messageType)->deleteOne($data, $this->redis);

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $data);

    }


    public function forward(array $data): array
    {
        $userId = $data['user_id'];

        $messageIds = explode(',', $data['forward_messages_id']);
        $forwardChatIds = explode(',', $data['chat_id']);


        // @TODO отрефакторить это собирать все id сообщений и делать 1 sql запрос пока пусть так для теста
        $responseData = [];

        $multiResponseData = [];

        $i = 0;
        foreach ($forwardChatIds as $chatId) {
            foreach ($messageIds as $messageId) {
                $redisForwardMessageData = $this->redis->hGetAll($messageId);
                $messageData = $redisForwardMessageData == [] ? Manager::table('messages')->where('redis_id', $messageId)->first()->toArray() : $redisForwardMessageData;
                $attachments = $messageData['attachments'] ?? null;

                $checkUserInChatMembers = UserHelper::CheckUserInChatMembers((int)$data['user_id'], $chatId, $this->redis);

                if ($messageData && $checkUserInChatMembers) {
                    // создать сообщение и добавить в чат

                    $messageRedisCount = $this->redis->incrBy("user:message:{$userId}", 1);

                    $messageRedisId = "message:{$userId}:$messageRedisCount";
                    $forwardText = $messageData['text'] ?? null;
                    $replyMessageId = $messageData['reply_message_id'] ?? null;


                    $replyData = null;
                    $replyMessageType = null;
                    if ($replyMessageId) {

                        $replyMessageType = $this->redis->hGet($replyMessageId, 'type') ?? Manager::table('messages')
                                ->where('redis_id', $replyMessageId)
                                ->orWhere('id', $replyMessageId)
                                ->value('type');

                        $replyMessageClass = Factory::getItem($replyMessageType);
                        $replyData = $replyMessageClass->getOriginalDataForReply($replyMessageId, $this->redis);
                    }

                    $messageType = $replyMessageType ?? $messageData['type'];


                    $this->redis->hSet($messageRedisId, 'text', $forwardText);
                    $this->redis->hSet($messageRedisId, 'chat_id', $chatId);
                    $this->redis->hSet($messageRedisId, 'user_id', $data['user_id']);
                    $this->redis->hSet($messageRedisId, 'status', MessageController::NO_WRITE);
                    $this->redis->hSet($messageRedisId, 'time', time());
                    $this->redis->hSet($messageRedisId, 'type', $messageType);
                    $this->redis->hSet($messageRedisId, 'attachments', $attachments);
                    $this->redis->hSet($messageRedisId, 'forward_message_id', $messageId);
                    $this->redis->hSet($messageRedisId, 'reply_message_id', $replyMessageId);


                    $avatar = $this->redis->get("user_avatar:{$messageData['user_id']}");
                    $forwardData = ForwardHelper::getForwardFields($messageData, $messageId, $this->redis);


                    $messageForType = MessageHelper::getAttachmentTypeString($messageData['type']) ?? null;

                    $multiResponseData['responses'][$i]['cmd'] = 'message:create';
                    $multiResponseData['responses'][$i]['notify_users'] = ChatHelper::getChatMembers($chatId, $this->redis);
                    $multiResponseData['responses'][$i]['data'] = [
                        "status" => true,
                        "write" => MessageController::NO_WRITE,
                        "chat_id" => $chatId,
                        "message_id" => $messageRedisId,
                        "user_id" => $data['user_id'],
                        "time" => time(),
                        "avatar" => $avatar == false ? "noAvatar.png" : $avatar,
                        "avatar_url" => MessageHelper::AVATAR_URL,
                        "user_name" => $this->redis->get("user:name:{$data['user_id']}"),
                        'forward_message_id' => $messageId,
                        'reply_message_id' => $replyMessageId,
                        'forward_data' => $forwardData,
                        'type' => MessageHelper::FORWARD_MESSAGE_TYPE,
                        'message_for_type' => $messageForType,
                    ];
                    ++$i;
                    // добавляем сообщение в общий список сообщений

                    $this->redis->zAdd('all:messages', ['NX'], self::NO_WRITE, "message:$userId:$messageId");

                    MessageHelper::addMessageInChat($this->redis, $chatId, $messageRedisId);

                    $chatMembersWithNotAuthor = ChatHelper::getChatMembers($chatId, $this->redis);
                    unset($chatMembersWithNotAuthor[$userId]);

                    // ставим последнее время для фильтрации чатов в списке пользователя
                    $this->redis->zAdd("user:chats:{$userId}", ['NX'], time(), $chatId);

                    ChatHelper::incrUnWriteCountForMembers($chatId, $this->redis, $chatMembersWithNotAuthor);
                }

            }
        }


        $multiResponseData['multi_response'] = true;

        $this->redis->close();
        return $multiResponseData;
    }


    /**
     * @param array $data
     * @return array
     */
    public function getChatMessageByType(array $data): array
    {
        $page = $data['page'] ?? 1;
        $onePageCount = 20;
        $type = ucfirst($data['type']);
        $responseItems = [];

        $start = $onePageCount * $page - $onePageCount;
        $messages = Manager::table('messages')
            ->where('status', '>=', 0)
            ->where('chat_id', $data['chat_id'])
            ->whereIn('type', ChatHelper::getMessageTypeForMessageListInChat($data['type']))
            ->orderByDesc('time')
            ->skip($start)
            ->take($onePageCount)
            ->get()
            ->toArray();

        $response = [];
        $response['pagination'] = [
            'page' => $page,
            'count' => $onePageCount
        ];

        $callFunctionForResponse = "get$type";

        foreach ($messages as $message) {

            if ($message->attachments) {

                $attachments = json_decode($message->attachments, true) ?? null;

                if ($attachments) {
                    foreach ($attachments as $attachment) {
                        $responseItems[] = GetResponseForMessageType::$callFunctionForResponse($message, $this->redis, $attachment);
                    }
                }
            } else {
                $responseItems[] = GetResponseForMessageType::$callFunctionForResponse($message, $this->redis);
            }


        }
        $response['data'] = $responseItems;

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $response);

    }

}