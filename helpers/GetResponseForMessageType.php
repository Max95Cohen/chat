<?php


namespace Helpers;


use Patterns\MessageFactory\Classes\DocumentMessage;
use Patterns\MessageFactory\Classes\VoiceMessage;
use Patterns\MessageFactory\Factory;
use Redis;
use Traits\RedisTrait;

class GetResponseForMessageType
{

    const MESSAGE_TYPES = [
        'Links',
        'Media',
        'Audio',
        'Files',
    ];



    /**
     * @param $message
     * @param Redis|null $redis
     * @param array|null $item
     * @return array
     */
    public static function getLinks(object $message, ?Redis $redis = null, array $item = null): array
    {
        return [
            'link' => $item['link'],
            'time' => $message->time
        ];
    }


    /**
     * @param array $message
     * @param Redis|null $redis
     * @param array|null $item
     * @return array
     */
    public static function getMedia(object $message, ?Redis $redis = null, array $item = null) :array
    {
        return [
            'file_name' => $item['filename'],
            'user_id' => $message->user_id,
            'user_name' => $redis->get("user:name:{$message->user_id}"),
            'type' => $message->type,
            'time' => $message->time,
            'media_url' => Factory::getItem($message->type)::getMediaUrl()
        ];

    }

    /**
     * @param object $message
     * @param Redis|null $redis
     * @param array|null $item
     * @return array
     */
    public static function getAudio(object $message, ?Redis $redis = null, array $item = null) :array
    {
        return [
            'file_name' => $item['filename'],
            'user_id' => $message->user_id,
            'user_name' => $redis->get("user:name:{$message->user_id}"),
            'type' => $message->type,
            'time' => $message->time,
            'media_url' => VoiceMessage::getMediaUrl()
        ];
    }

    /**
     * @param array $message
     * @param Redis|null $redis
     * @param array|null $item
     * @return array
     */
    public static function getFiles(object $message, ?Redis $redis = null, array $item = null) :array
    {
        return [
            'file_name' => $item['filename'],
            'user_id' => $message->user_id,
            'user_name' => $redis->get("user:name:{$message->user_id}"),
            'type' => $message->type,
            'time' => $message->time,
            'media_url' => DocumentMessage::getMediaUrl()
        ];

    }

}