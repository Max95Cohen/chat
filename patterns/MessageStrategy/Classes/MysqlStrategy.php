<?php


namespace Patterns\MessageStrategy\Classes;


use Carbon\Carbon;
use Controllers\MessageController;
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
//        dump($allMessages);

        $firstMessageId = $allMessages->first() ? $allMessages->first()->id : null;
        $lastMessageId = $allMessages->last() ? $allMessages->last()->id : null;


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
                'attachment_url' => method_exists($messageClass,'getMediaUrl') ? $messageClass::getMediaUrl() : null,
                'reply_data' => $replyMessageId ? $messageClass->getOriginalDataForReply($message->id, $this->redis) : null,
                'write' =>(string) $message->status,
                'edit' => $edit,
            ];
            dump($message->user_id,$message->status);
            if ($message->status == MessageController::NO_WRITE && $message->user_id != $data['user_id']) {
                $this->redis->watch("chat:unwrite:count:{$chatId}");
                $unwriteCount = intval($this->redis->get("chat:unwrite:count:{$chatId}"));
                dump($unwriteCount);
                if ($unwriteCount > 0) {
                    $unwriteCount-=1;
                    $this->redis->multi();
                    $this->redis->set("chat:unwrite:count:{$chatId}",$unwriteCount);
                    $this->redis->exec();
                }
            }

        }

        // обновляю статус на прочитанное
        if ($allMessages->last()) {
            if ($allMessages->last()->user_id != $data['user_id']) {
                Manager::table('messages')
                    ->where('id','>=',$firstMessageId)
                    ->where('id','<=',$lastMessageId)
                    ->update(['status' => MessageController::WRITE]);
            }
        }



        $this->redis->close();
        return $messagesForDivider;
    }

}