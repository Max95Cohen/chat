<?php

namespace Patterns\MessageFactory;

use Helpers\MessageHelper;
use Patterns\MessageFactory\Classes\DocumentMessage;
use Patterns\MessageFactory\Classes\ForwardMessage;
use Patterns\MessageFactory\Classes\GeoPointMessage;
use Patterns\MessageFactory\Classes\ImageMessage;
use Patterns\MessageFactory\Classes\LinkMessage;
use Patterns\MessageFactory\Classes\MoneyMessage;
use Patterns\MessageFactory\Classes\ReplyMessage;
use Patterns\MessageFactory\Classes\StickerMessage;
use Patterns\MessageFactory\Classes\TextMessage;
use Patterns\MessageFactory\Classes\VideoMessage;
use Patterns\MessageFactory\Classes\VoiceMessage;


class Factory
{

    public static function getItem(int $type)
    {
        switch ($type) {

            case  MessageHelper::TEXT_MESSAGE_TYPE :
                return new TextMessage();
            case MessageHelper::IMAGE_MESSAGE_TYPE :
                return new ImageMessage();
            case MessageHelper::DOCUMENT_MESSAGE_TYPE:
                return new DocumentMessage();
            case MessageHelper::VOICE_MESSAGE_TYPE:
                return new VoiceMessage();
            case MessageHelper::VIDEO_MESSAGE_TYPE:
                return new VideoMessage();
            case MessageHelper::GEO_POINT_MESSAGE_TYPE:
                return new GeoPointMessage();
            case MessageHelper::REPLY_MESSAGE_TYPE:
                return new ReplyMessage();
            case MessageHelper::LINK_MESSAGE_TYPE:
                return new LinkMessage();
            case MessageHelper::MONEY_MESSAGE_TYPE:
                return new MoneyMessage();
            case MessageHelper::STICKER_MESSAGE_TYPE:
                return new StickerMessage();
            case MessageHelper::FORWARD_MESSAGE_TYPE:
                return new ForwardMessage();

        }


    }

}