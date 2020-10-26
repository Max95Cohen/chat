<?php

namespace Helpers;

use Redis;

class Helper
{
    private static $singleton;

    public function __construct()
    {
        if (file_exists('.env')) {
            $env = parse_ini_file('.env');

            $redis = new Redis;
            $redis->connect('127.0.0.1', 6379);

            foreach ($env as $key => $value) {
                if (!empty(trim($value))) {
                    $redis->hSet('helper:env', $key, $value);
                }
            }

            $redis->close();
        }
    }

    public static function log($string, $prefix = false)
    {
        if (!self::get('DEBUG')) {
            return false;
        }

        if ($prefix) {
            echo $prefix . ' : ';
        }

        if ($string) {
            if (gettype($string) == 'array' && isset($string['server']) && get_class($string['server']) == 'Swoole\WebSocket\Server') {
                unset($string['server']);
            }

            print_r($string); # not remove!

            if (gettype($string) == 'string') {
                echo "\n";
            }
        } else {
            echo "\n";
        }

        return true;
    }

    private static function singleton()
    {
        if (!self::$singleton) {
            self::$singleton = new Helper();
        }

        return self::$singleton;
    }

    public static function get($variable)
    {
        Helper::singleton();

        $redis = new Redis;
        $redis->connect('127.0.0.1', 6379);
        $value = $redis->hGet('helper:env', $variable);
        $redis->close();

        if (!$value) {
            $value = '';
        }

        return $value;
    }
}
