<?php

namespace Patterns\MessageFactory;

use Helpers\MessageHelper;
use Patterns\MessageFactory\Classes\DocumentMessage;
use Patterns\MessageFactory\Classes\ImageMessage;
use Patterns\MessageFactory\Classes\TextMessage;
use Patterns\MessageFactory\Classes\VoiceMessage;


class Factory
{

    public static function getItem(int $type)
    {
        switch ($type) {

            case  MessageHelper::TEXT_MESSAGE_TYPE :
                return new TextMessage();

            case MessageHelper::IMAGE_MESSAGE_TYPE :
                return  new ImageMessage();

            case MessageHelper::DOCUMENT_MESSAGE_TYPE:
                return new DocumentMessage();

            case MessageHelper::VOICE_MESSAGE_TYPE:
                return new VoiceMessage();
        }



    }

}