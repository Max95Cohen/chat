<?php


namespace Patterns\MessageFactory\Classes;

use Helpers\MediaHelper;
use Helpers\MessageHelper;
use Patterns\MessageFactory\Interfaces\MediaMessageInterface;
use Redis;
use \Swoole\Http\Request;
use Patterns\MessageFactory\Interfaces\MessageInterface;

class VoiceMessage implements MessageInterface, MediaMessageInterface
{

    const VOICE_MEDIA_ULR = 'voice';
    const VOICE_MEDIA_DIR = 'media/voice';

    /**
     * @return string
     */
    public static function getMediaDir() :string
    {
        return MEDIA_DIR . '/' . self::VOICE_MEDIA_DIR;
    }

    /**
     * @return string
     */
    public static function getMediaUrl() :string
    {
        return MEDIA_URL . self::VOICE_MEDIA_ULR;
    }


    /**
     * @param Request $request
     * @return array
     */
    public function upload(Request $request) :array
    {
        $extension = '.mp3';
        $fileName  = MediaHelper::generateFileName($extension);
        move_uploaded_file( $request->files['file']['tmp_name'],self::getMediaDir() . "/{$fileName}");

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
        $redis->hSet('type','type',MessageHelper::VOICE_MESSAGE_TYPE);
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

        $messageData['media'] = $data['media'];
        $messageData['type'] = MessageHelper::VOICE_MESSAGE_TYPE;
        return $messageData;
    }

}