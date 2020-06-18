<?php

namespace Patterns\ChatFactory;

use Controllers\ChatController;
use Patterns\ChatFactory\Classes\GroupChat;
use Patterns\ChatFactory\Classes\PrivateChat;



class Factory
{
    public static function getItem(int $type)
    {
        switch ($type) {

            case ChatController::PRIVATE :
                return new PrivateChat();
            case ChatController::GROUP:
                return new GroupChat();

        }
    }


}