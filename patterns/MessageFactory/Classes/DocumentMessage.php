<?php


namespace Patterns\MessageFactory\Classes;


use Helpers\MediaHelper;
use Helpers\MessageHelper;
use Patterns\MessageFactory\Interfaces\MediaMessageInterface;
use Patterns\MessageFactory\Interfaces\MessageInterface;
use Redis;
use Swoole\Http\Request;

class DocumentMessage implements MessageInterface,MediaMessageInterface
{

    const DOCUMENT_MEDIA_ULR = 'media/documents';
    const DOCUMENT_MEDIA_DIR = 'documents';

    public function returnData(array $params): array
    {
        return [];
    }

    public static function getMediaDir()
    {
        return MEDIA_DIR . '/' . self::DOCUMENT_MEDIA_ULR;
    }

    public static function getMediaUrl()
    {
        return MEDIA_URL . self::DOCUMENT_MEDIA_DIR;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function upload(Request $request) :array
    {
        $mimeType = mime_content_type($request->files['file']['tmp_name']);

        $extension = MediaHelper::getExtensionByMimeType($mimeType);
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
    public function addExtraFields(Redis $redis, string $redisKey, array $data) :void
    {
        $redis->hSet($redisKey,'type',MessageHelper::DOCUMENT_MESSAGE_TYPE);
        $redis->hSet($redisKey,'attachments',json_encode($data['attachments']));
    }

    /**
     * @param array $data
     * @param string $messageRedisKey
     * @param Redis $redis
     * @return array
     */
    public function returnResponseDataForCreateMessage(array $data, string $messageRedisKey, Redis $redis): array
    {
        $messageData = MessageHelper::getResponseDataForCreateMessage($data,$messageRedisKey,$redis);

        $messageData['attachments'] = $redis->hGet($messageRedisKey,'attachments');
        $messageData['attachment_url'] = self::getMediaUrl();
        $messageData['type'] = MessageHelper::DOCUMENT_MESSAGE_TYPE;
        return $messageData;
    }

    /**
     * @param $data
     * @param Redis $redis
     * @return array
     */
    public function editMessage($data, Redis $redis)
    {
        MediaHelper::editInRedis($data, $redis);
        MediaHelper::editInMysql($data);
        //@TODO тут будет логика для удаления старых файлов и.т.д
        return [
            'message_id' => $data['message_id'],
            'status' => MessageHelper::MESSAGE_EDITED_STATUS,
            'chat_id' => $data['chat_id'],
            'attachments' => $data['attachments'],
            'user_id' => $data['user_id']
        ];
    }

    /**
     * @param array $data
     * @param Redis $redis
     * @return array
     */
    public function deleteMessage(array $data, Redis $redis) :array
    {
        MessageHelper::deleteMessageInRedis($data,$redis);
        MessageHelper::deleteMessageInMysql($data);
        //@TODO тут будет логика для удаления старых файлов и.т.д
        return [
            'message_id' => $data['message_id'],
            'status' => MessageHelper::MESSAGE_DELETED_STATUS,
            'chat_id' => $data['chat_id'],
            'user_id' => $data['user_id'],
        ];

    }




}