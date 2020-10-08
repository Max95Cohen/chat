<?php

namespace Helpers;

class Helper
{
    public static function log($string, $prefix = false)
    {
        if ($prefix) {
            echo $prefix . ' : ';
        }

        if ($string) {
            if (isset($string['server']) && get_class($string['server']) == 'Swoole\WebSocket\Server') {
                unset($string['server']);
            }

            print_r($string);

            if (gettype($string) == 'string') {
                echo "\n";
            }
        }
    }
}
