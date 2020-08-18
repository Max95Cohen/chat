<?php


namespace Helpers;


use Patterns\MessageFactory\Classes\VoiceMessage;
use Redis;
use Traits\RedisTrait;

class GetResponseForMessageType
{

    public static function getLinks(array $items, $message, ?Redis $redis=null) :array
    {
        $links = collect(json_decode($message->attachments,true))->pluck('link')->toArray();
        dump(collect(array_merge($items,$links))->flatten()->toArray());
        return  collect(array_merge($items,$links))->flatten()->toArray();
    }


    public static function getMedia(array $items,array $message,?Redis $redis=null)
    {

    }

    public static function getAudio(array $items, object $message,?Redis $redis=null)
    {
        $audio = json_decode($message->attachments,true)[0]['filename'];

        $audioData = [
            'file_name' => $audio,
            'user_id' => $message->user_id,
            'user_name' => $redis->get("user:name:{$message->user_id}"),
            'time' => $message->time,
            'media_url' => VoiceMessage::getMediaUrl()
        ];
        return array_merge($items,$audioData);

    }

    public static function getFiles(array $items,array $message)
    {



    }

}