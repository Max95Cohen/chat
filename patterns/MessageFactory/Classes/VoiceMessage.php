<?php


namespace Patterns\MessageFactory\Classes;

use Helpers\MediaHelper;
use Helpers\MessageHelper;
use Illuminate\Database\Capsule\Manager;
use Patterns\MessageFactory\Interfaces\MediaMessageInterface;
use Redis;
use \Swoole\Http\Request;
use Patterns\MessageFactory\Interfaces\MessageInterface;

class VoiceMessage implements MessageInterface, MediaMessageInterface
{

    const VOICE_MEDIA_ULR = 'voice';
    const VOICE_MEDIA_DIR = 'media/voice/';

    /**
     * @return string
     */
    public static function getMediaDir(): string
    {
        return MEDIA_DIR . '/' . self::VOICE_MEDIA_DIR;
    }

    /**
     * @return string
     */
    public static function getMediaUrl(): string
    {
        return MEDIA_URL . self::VOICE_MEDIA_ULR . '/';
    }


    /**
     * @param Request $request
     * @return array
     */
    public function upload(Request $request): array
    {
        $extension = '.mp3';
        $fileName = MediaHelper::generateFileName($extension);
        move_uploaded_file($request->files['file']['tmp_name'], self::getMediaDir() . "/{$fileName}");
        return [
            'data' => [
                'status' => true,
                'media_url' => self::getMediaUrl(),
                'file_name' => $fileName,
            ],
            'file_name' => $fileName
        ];


    }

    /**
     * @param Redis $redis
     * @param string $redisKey
     * @param array $data
     */
    public function addExtraFields(Redis $redis, string $redisKey, array $data): void
    {
        $redis->hSet($redisKey, 'type', MessageHelper::VOICE_MESSAGE_TYPE);
        $redis->hSet($redisKey, 'attachments', json_encode($data['attachments']));
    }

    /**
     * @param array $data
     * @param string $messageRedisKey
     * @param Redis $redis
     * @return array
     */
    public function returnResponseDataForCreateMessage(array $data, string $messageRedisKey, Redis $redis): array
    {
        $messageData = MessageHelper::getResponseDataForCreateMessage($data, $messageRedisKey, $redis);

        $messageData['attachments'] = $redis->hGet($messageRedisKey, 'attachments');
        $messageData['attachmentsNew'] = json_decode($redis->hGet($messageRedisKey, 'attachments'), true);
        $messageData['attachment_url'] = self::getMediaUrl();
        $messageData['type'] = MessageHelper::VOICE_MESSAGE_TYPE;
        $messageData['message_text_for_type'] = MessageHelper::getAttachmentTypeString(MessageHelper::VOICE_MESSAGE_TYPE) ?? null;

        return $messageData;
    }


    /**
     * @param $messageId
     * @param Redis $redis
     * @return array
     */
    public function getOriginalDataForReply($messageId, Redis $redis)
    {
        // @TODO ?????? ???????? ?????????????? ?? ?????????????????? ????????????
        $messageDataInRedis = $redis->hGetAll($messageId);

        $messageData = $messageDataInRedis == false
            ? Manager::table('messages')->where('id', $messageId)->first(['id', 'user_id', 'attachments'])->toArray()
            : $messageDataInRedis;
        //@TODO ?????????????????? ?????????? ???????????????????? ???????????? ?????????????? ???????? ?????????? ?????? ???????? ?????????????? ???????? ?? ?????????? ... ???????????????? ?? 1 ????????????

        $attachments = json_decode($messageData['attachments'], true);

        return [
            'message_id' => $messageId,
            'type' => MessageHelper::VOICE_MESSAGE_TYPE,
            'user_avatar' => $redis->get("user:avatar:{$messageData['user_id']}"),
            'user_name' => $redis->get("user:name:{$messageData['user_id']}"),
            'user_id' => $messageData['user_id'],
            'attachments' => array_shift($attachments),
            'attachments_url' => self::getMediaUrl(),
            'message_text_for_type' => MessageHelper::getAttachmentTypeString(MessageHelper::VOICE_MESSAGE_TYPE),
            'is_deleted' => $messageData['status'] == MessageHelper::MESSAGE_DELETED_STATUS,
        ];
    }

    /**
     * @param array $data
     * @param Redis $redis
     * @return array
     */
    public function deleteMessage(array $data, Redis $redis): array
    {
        MessageHelper::deleteMessageInRedis($data, $redis);
        MessageHelper::updateMessageStatusInMysql($data, MessageHelper::MESSAGE_DELETED_STATUS);

        return [
            'message_id' => $data['message_id'],
            'status' => MessageHelper::MESSAGE_DELETED_STATUS,
            'chat_id' => $data['chat_id'],
            'user_id' => $data['user_id'],
        ];

    }

    /**
     * @param $data
     * @param Redis $redis
     * @return array
     */
    public function editMessage($data, Redis $redis)
    {
        MediaHelper::messageEditInRedis($data, $redis);

        # TODO update in MySQL DB;
//        MediaHelper::messageEditInMysql($data);

        //@TODO ?????? ?????????? ???????????? ?????? ???????????????? ???????????? ???????????? ??.??.??

        if (!isset($data['attachments'])) {
            $attachments = $redis->hGet($data['message_id'], 'attachments');
            $data['attachments'] = $attachments;

            $attachments = json_decode($attachments, true);

            $data['attachmentsNew'] = $attachments;
        }

        return [
            'chat_id' => $data['chat_id'],
            'attachments' => $data['attachments'],
            'attachmentsNew' => $data['attachmentsNew'],
            'attachment_url' => self::getMediaUrl(),
            'message_id' => $data['message_id'],
            'text' => $data['text'],
            'user_id' => $data['user_id'],
            'time' => $data['time'],
            'avatar' => $redis->get("user:avatar:{$data['user_id']}") ?? '',
            'user_name' => $redis->get("user:name:{$data['user_id']}") ?? '',
            'avatar_url' => 'https://indigo24.xyz/uploads/avatars/',
            'type' => $data['message_type'],
            'edit' => 1,
        ];
    }
}
