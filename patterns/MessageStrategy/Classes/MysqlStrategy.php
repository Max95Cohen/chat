<?php


namespace Patterns\MessageStrategy\Classes;


use Carbon\Carbon;
use Controllers\MessageController;
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
        $allMessages = Manager::table('messages')
            ->orderBy('time', 'desc')
            ->where('chat_id', $data['chat_id'])
            ->where("status",'>=',MessageHelper::MESSAGE_NO_WRITE_STATUS)
            ->skip(($data['page'] * $data['count']) - $data['count'])
            ->take($data['count'])
            ->get();

        $writeCount = $allMessages->where('user_id', '!=', $data['user_id'])->where('status', MessageController::NO_WRITE)->count();;


        if ($allMessages->last()->user_id !== $data['user_id']) {
            Manager::table('messages')
                ->orderBy('time', 'desc')
                ->where('chat_id', $data['chat_id'])
                ->skip(($data['page'] * $data['count']) - $data['count'])
                ->update(['status' => MessageController::WRITE]);
        }

        $messagesForDivider = [];
        $attachments = $message['attachments'] ?? null;
        foreach ($allMessages as $message) {
            // возвращаю сообщения в корректном формате
            $messageType = $message->type ?? 0;

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
                'day' => Carbon::parse($message->time)->format('d-m-Y'),
                'hour' => Carbon::parse($message->time)->format('H:i'),
                'attachments' => $attachments,
                'reply_data' => Factory::getItem($messageType)->getOriginalDataForReply($message->id,$this->redis) ?? null
            ];
            $allDays = collect($messagesForDivider)->pluck('day')->unique()->toArray();

            $messagesWithDivider = [];
            foreach ($allDays as $day) {
                $messagesWithDivider[$day] = collect($messagesForDivider)->where('day', $day)->toArray();
            }

            $responseDataWithDivider = [];

            foreach ($messagesWithDivider as $date => $dividerData) {

                $dividerData = array_combine(range(1, count($dividerData)), $dividerData);
                $dividerData[0] = [
                    'text' => $date,
                    'type' => 'divider',
                ];
                sort($dividerData);
                $responseDataWithDivider[] = [...$responseDataWithDivider,$dividerData];
            }

        }

        $writeCount = $writeCount != 0 ? -1 * $writeCount : 0;

        $this->redis->incrBy("chat:unwrite:count:{$data['chat_id']}", $writeCount);
            $this->redis->close();
            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], $responseDataWithDivider[0]);
    }

}