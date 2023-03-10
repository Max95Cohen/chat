<?php


namespace Patterns\MessageStrategy\Classes;


use Carbon\Carbon;
use Controllers\MessageController;
use Helpers\ForwardHelper;
use Helpers\MediaHelper;
use Helpers\MessageHelper;
use Helpers\ResponseFormatHelper;
use Illuminate\Database\Capsule\Manager;
use Patterns\MessageFactory\Factory;
use Redis;

class MysqlStrategy
{
    private $redis;

    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1');
    }

    /**
     * @param array $data
     * @return array
     */
    public function getMessages(array $data): array
    {

        $chatId = $data['chat_id'];

        $allMessages = Manager::table('messages')
            ->orderBy('time', 'desc')
            ->where('chat_id', $chatId)
            ->where("status", '>=', MessageHelper::MESSAGE_NO_WRITE_STATUS)
            ->skip(($data['page'] * $data['count']) - $data['count'])
            ->take($data['count'])
            ->get();

        $firstMessageId = $allMessages->first() ? $allMessages->first()->id : null;
        $lastMessageId = $allMessages->last() ? $allMessages->last()->id : null;


        $messagesForDivider = [];
        $attachments = $message['attachments'] ?? null;
        foreach ($allMessages as $message) {
            // возвращаю сообщения в корректном формате
            $messageType = $message->type ?? 0;
            $replyMessageId = $message->reply_message_id ?? null;

            if ($replyMessageId) {
                $replyMessageType = $this->redis->hGet($replyMessageId, 'type') ?? Manager::table('messages')
                        ->where('redis_id', $replyMessageId)
                        ->orWhere('id', $replyMessageId)
                        ->value('type');
                $replyMessageClass = Factory::getItem($replyMessageType);
            }

            $forwardMessageId = $message->forward_message_id;
            $forwardData = null;
            if ($forwardMessageId) {
                $forwardMessage = $this->redis->hGetAll($forwardMessageId) ?? Manager::table("messages")->where('id', $forwardMessageId)->first()->toArray();
                $forwardData =ForwardHelper::getForwardFields($forwardMessage,$firstMessageId,$this->redis);

            }
            $messageClass = Factory::getItem($messageType);
            $edit = $message->edit ?? 0;


            if ($messageType == MessageHelper::STICKER_MESSAGE_TYPE && $attachments) {
                $attachments = json_decode($attachments,true);
                $stickerId = $attachments[0]['stick_id'];

                $sticker = $this->redis->hGetAll("sticker:{$stickerId}");

                $attachments = json_encode([
                    'stick_id' => $stickerId,
                    'path' => $sticker['path']
                ],JSON_UNESCAPED_UNICODE);

            }

            $messagesForDivider[] = [
                'id' => strval($message->redis_id),
                'user_id' => $message->user_id,
                'avatar' => $this->redis->get("user:avatar:{$message->user_id}"),
                'phone' => $this->redis->get("user:phone:{$message->user_id}"),
                'avatar_url' => MessageHelper::AVATAR_URL,
                'user_name' => $this->redis->get("user:name:{$message->user_id}"),
                'text' => $message->text ?? null,
                'chat_id' => $message->chat_id,
                'type' => $messageType,
                'time' => $message->time,
                'attachments' => $attachments,
                'attachment_url' => method_exists($messageClass, 'getMediaUrl') ? $messageClass::getMediaUrl() : null,
                'reply_data' => $replyMessageId ? $replyMessageClass->getOriginalDataForReply($replyMessageId, $this->redis) : null,
                'forward_data' => $forwardData ? json_encode($forwardData) : null,
                'forward_dataNew' => $forwardData ?: null,
                'message_for_type' => MessageHelper::getAttachmentTypeString($message->type),
                'write' => (string)$message->status,
                'edit' => $edit,
            ];

        }

        // обновляю статус на прочитанное
        if ($allMessages->last()) {
            if ($allMessages->last()->user_id != $data['user_id']) {
                Manager::table('messages')
                    ->where('id', '>=', $firstMessageId)
                    ->where('id', '<=', $lastMessageId)
                    ->update(['status' => MessageController::WRITE]);
            }
        }


        $this->redis->close();
        return $messagesForDivider;
    }

}