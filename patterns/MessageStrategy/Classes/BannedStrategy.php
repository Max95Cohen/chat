<?php


namespace Patterns\MessageStrategy\Classes;


use Controllers\MessageController;
use Helpers\MessageHelper;
use Illuminate\Database\Capsule\Manager;
use Patterns\MessageFactory\Factory;
use Patterns\MessageStrategy\Interfaces\MessageStrategyInterface;
use Traits\RedisTrait;

class BannedStrategy implements MessageStrategyInterface
{
    use RedisTrait;

    /**
     * @param array $data
     * @return array
     */
    public function getMessages(array $data): array
    {
        $chatId = $data['chat_id'];

        $userBannedTime = Manager::table('chat_members')
            ->where('chat_id',$data['chat_id'])
            ->where('user_id',$data['user_id'])
            ->value('banned_time');


        $allMessages = Manager::table('messages')
            ->orderBy('time', 'desc')
            ->where('chat_id', $chatId)
            ->where("status", '>=', MessageHelper::MESSAGE_NO_WRITE_STATUS)
            ->where('time','<=',$userBannedTime)
            ->skip(($data['page'] * $data['count']) - $data['count'])
            ->take($data['count'])
            ->get();
        $messagesForDivider = [];
        $attachments = $message['attachments'] ?? null;
        $responseDataWithDivider = [];
        foreach ($allMessages as $message) {
            // возвращаю сообщения в корректном формате
            $messageType = $message->type ?? 0;
            $replyMessageId = $message->reply_message_id ?? null;

            $messageClass = Factory::getItem($messageType);
            $edit = $message->edit ?? 0;

            $messagesForDivider[] = [
                'id' => strval($message->id),
                'user_id' => $message->user_id,
                'avatar' => $this->redis->get("user:avatar:{$message->user_id}"),
                'avatar_url' => MessageHelper::AVATAR_URL,
                'user_name' => $this->redis->get("user:name:{$message->user_id}"),
                'text' => $message->text ?? null,
                'chat_id' => $message->chat_id,
                'type' => $messageType,
                'time' => $message->time,
                'attachments' => $attachments,
                'attachment_url' => method_exists($messageClass, 'getMediaUrl') ? $messageClass::getMediaUrl() : null,
                'reply_data' => $replyMessageId ? $messageClass->getOriginalDataForReply($message->id, $this->redis) : null,
                'write' => (string)$message->status,
                'edit' => $edit,
            ];


        }

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