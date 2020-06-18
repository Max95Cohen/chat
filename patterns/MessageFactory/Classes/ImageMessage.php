<?php

namespace Patterns\MessageFactory\Classes;

use Controllers\MessageController;
use Gumlet\ImageResize;
use Helpers\MediaHelper;
use Helpers\MessageHelper;
use Illuminate\Support\Str;
use Patterns\MessageFactory\Interfaces\MediaMessageInterface;
use Patterns\MessageFactory\Interfaces\MessageInterface;
use Redis;
use \Swoole\Http\Request;


class ImageMessage implements MessageInterface, MediaMessageInterface
{

    const IMAGE_MEDIA_ULR = 'images';
    const IMAGE_MEDIA_DIR = 'media/images';

    public static function getMediaDir()
    {
        return MEDIA_DIR . '/' . self::IMAGE_MEDIA_DIR;
    }

    public static function getMediaUrl()
    {
        return MEDIA_URL . self::IMAGE_MEDIA_ULR .'/';
    }


    public function returnData(array $params): array
    {
        return [
            'media_url' => self::getMediaUrl(),

        ];
    }


    public function handle(string $function, $params)
    {
        return $this->{$function}($params);
    }


    // в будущем будет сжимать изображение

    private function compress()
    {

    }

    /**
     * @param Request $request
     * @return array
     */
    public function upload(Request $request) :array
    {

        $extension = '.jpg';
        $fileName  = Str::random(rand(30,35));

        $fullSizeFileName = $fileName . $extension;
        $resizeFileName = $fileName ."_200x200".$extension;


        $filePath = self::getMediaDir() . "/{$fullSizeFileName}";

        move_uploaded_file($request->files['file']['tmp_name'],$filePath);


        $image = new ImageResize($filePath);
        $image->crop(200,200,true,ImageResize::CROPCENTER);
        $image->save(self::getMediaDir() ."/{$resizeFileName}");

        return [
            'data' => [
                'status' => true,
                'media_url' => self::getMediaUrl(),
                'file_name' => $fullSizeFileName,
                'resize_file_name' => $resizeFileName,
            ],
            'file_name' => $fileName
        ];

    }

    public function create()
    {

    }


    /**
     * @param Redis $redis
     * @param string $redisKey
     * @param array $data
     */
    public function addExtraFields(Redis $redis, string $redisKey, array $data) :void
    {
        $redis->hSet($redisKey,'type',MessageHelper::IMAGE_MESSAGE_TYPE);

        var_dump(json_encode($data['attachments']));
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
        $messageData['type'] = MessageHelper::IMAGE_MESSAGE_TYPE;

        return $messageData;
    }

    /**
     * @param array $data
     * @param Redis $redis
     * @return array
     */
    public function deleteOne(array $data, Redis $redis) :array
    {
        $messageId = $data['message_id'];

        $checkInRedis = $redis->exists($messageId);

        if ($checkInRedis) {
            $redis->hMSet($messageId,['status' => MessageHelper::MESSAGE_DELETED_SELF_STATUS]);
            $redis->set("self:deleted:{$messageId}",1);
        }

        if (MessageHelper::checkMessageExistInMysql($messageId)) {
            MessageHelper::updateMessageStatusInMysql($data,MessageHelper::MESSAGE_DELETED_SELF_STATUS);
        }
        //@TODO тут будет логика для удаления старых файлов и.т.д

        $redis->close();
        return [
            'message_id' => $data['message_id'],
            'status' => true,
            'user_id' => $data['user_id']
        ];

    }

}