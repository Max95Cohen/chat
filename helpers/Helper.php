<?php

namespace Helpers;

class Helper
{
    public static function log($string, $prefix = false)
    {
        $env = [];

        if (file_exists('.env')) {
            $env = parse_ini_file('.env');
        }

        if (!isset($env['DEBUG']) || !$env['DEBUG']) {
            return;
        }

        if ($prefix) {
            echo $prefix . ' : ';
        }

        if ($string) {
            if (gettype($string) == 'array' && isset($string['server']) && get_class($string['server']) == 'Swoole\WebSocket\Server') {
                unset($string['server']);
            }

            print_r($string);

            if (gettype($string) == 'string') {
                echo "\n";
            }
        } else {
            echo "\n";
        }
    }
}
