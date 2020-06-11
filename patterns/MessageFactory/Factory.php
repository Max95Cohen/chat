<?php

namespace Patterns\MessageFactory;

use Patterns\MessageFactory\Classes\DocumentMessage;
use Patterns\MessageFactory\Classes\ImageMessage;
use Patterns\MessageFactory\Classes\TextMessage;
use Patterns\MessageFactory\Classes\VoiceMessage;


class Factory
{

    const TEXT_TYPE = 0;
    const IMAGE_TYPE = 1;
    const DOCUMENT_TYPE =2;
    const VOICE_TYPE = 3;

    public static function getItem(int $type)
    {
        switch ($type) {

            case  self::TEXT_TYPE :
                return new TextMessage();

            case self::IMAGE_TYPE :
                return  new ImageMessage();

            case self::DOCUMENT_TYPE:
                return new DocumentMessage();

            case self::VOICE_TYPE:
                return new VoiceMessage();
        }



    }

}