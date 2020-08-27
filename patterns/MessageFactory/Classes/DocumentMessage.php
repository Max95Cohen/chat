<?php


namespace Patterns\MessageFactory\Classes;


use Helpers\MediaHelper;
use Helpers\MessageHelper;
use Illuminate\Database\Capsule\Manager;
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
                'extension' =>$extension,
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
        $redis->hSet($redisKey,'extension',$data['extension']);
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
        $messageData['extension'] = $redis->hGet($messageRedisKey,'extension');
        return $messageData;
    }

    /**
     * @param $data
     * @param Redis $redis
     * @return array
     */
    public function editMessage($data, Redis $redis)
    {
        MediaHelper::messageEditInRedis($data, $redis);
        MediaHelper::messageEditInMysql($data);
        //@TODO тут будет логика для удаления старых файлов и.т.д
        return [
            'chat_id' => $data['chat_id'],
            'attachments' => $data['attachments'],
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

    /**
     * @param array $data
     * @param Redis $redis
     * @return array
     */
    public function deleteMessage(array $data, Redis $redis) :array
    {
        MessageHelper::deleteMessageInRedis($data,$redis);
        MessageHelper::updateMessageStatusInMysql($data,MessageHelper::MESSAGE_DELETED_STATUS);
        //@TODO тут будет логика для удаления старых файлов и.т.д
        return [
            'message_id' => $data['message_id'],
            'status' => MessageHelper::MESSAGE_DELETED_STATUS,
            'chat_id' => $data['chat_id'],
            'user_id' => $data['user_id'],
        ];

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

    /**
     * @param $messageId
     * @param Redis $redis
     * @return array
     */
    public function getOriginalDataForReply($messageId, Redis $redis)
    {
        // @TODO это тоже вынести в отдельный хелпер
        $messageDataInRedis = $redis->hGetAll($messageId);

        $messageData = $messageDataInRedis == false
            ? Manager::table('messages')->where('id', $messageId)->first(['id', 'user_id', 'attachments'])->toArray()
            : $messageDataInRedis;
        //@TODO преписать через хелперскую функию вынести туда общие для всех классов поля и черех ... собирать в 1 массив

        $attachments = json_decode($messageData['attachments'],true);

        return [
            'message_id' => $messageId,
            'type' => MessageHelper::DOCUMENT_MESSAGE_TYPE,
            'user_avatar' => $redis->get("user:avatar:{$messageData['user_id']}"),
            'user_name' => $redis->get("user:name:{$messageData['user_id']}"),
            'user_id' => $messageData['user_id'],
            'attachments' => array_shift($attachments),
            'attachments_url' => self::getMediaUrl(),
            'message_text_for_type' => MessageHelper::getAttachmentTypeString(MessageHelper::DOCUMENT_MESSAGE_TYPE),
            'is_deleted' => $messageData['status'] == MessageHelper::MESSAGE_DELETED_STATUS,
        ];
    }



}