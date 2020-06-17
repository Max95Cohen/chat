<?php


namespace Patterns\MessageFactory\Classes;


use Helpers\MediaHelper;
use Helpers\MessageHelper;
use Illuminate\Support\Str;
use Redis;
use Swoole\Http\Request;

class VideoMessage
{
    const VIDEO_MEDIA_ULR = 'video/';
    const VIDEO_MEDIA_DIR = 'media/video/';


    /**
     * @return string
     */
    public static function getMediaDir() :string
    {
        return MEDIA_DIR . '/' . self::VIDEO_MEDIA_DIR;
    }

    /**
     * @return string
     */
    public static function getMediaUrl() :string
    {
        return MEDIA_URL . self::VIDEO_MEDIA_ULR;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function upload(Request $request) :array
    {
        $extension = '.mp4';
        $fileName = Str::random(rand(30,35));
        $fileNameWithExtension  = $fileName.$extension;
        $filePath = self::getMediaDir() . "/{$fileNameWithExtension}";
        $resizeFileName = "{$fileName}_360p_$extension";

        move_uploaded_file($request->files['file']['tmp_name'],$filePath);
//        $command = "/usr/bin/ffmpeg -i {$filePath} -b:v 350K -bufsize 350K /var/www/media.chat.indigo24/media/video/{$resizeFileName}";

//        system($command);

        // @TODO пока сжатие прям здесь потом перенести


        return [
            'data' => [
                'status' => true,
                'media_url' => self::getMediaUrl(),
                'file_name' => $fileNameWithExtension,
//                'resize_file_names' => $resizeFileName,
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
        $redis->hSet($redisKey,'type',MessageHelper::VIDEO_MESSAGE_TYPE);
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
        $messageData['type'] = MessageHelper::VIDEO_MESSAGE_TYPE;
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

}