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
        $extension = MediaHelper::getExtensionByMimeType($request->files['file']['type']);
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
        $redis->hSet('type','type',MessageHelper::DOCUMENT_MESSAGE_TYPE);
        $redis->hSet($redisKey,'attachments',$data['attachments']);
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

        $messageData['attachments'] = $data['attachments'];
        $messageData['attachment_url'] = self::getMediaUrl();
        $messageData['type'] = MessageHelper::DOCUMENT_MESSAGE_TYPE;
        return $messageData;
    }

}