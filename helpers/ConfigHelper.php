<?php


namespace Helpers;



class ConfigHelper
{


    /**
     * @param string $
     * @return mixed
     */
    public static function getDbConfig(string $dbConfName)
    {
        $path = dirname(__DIR__) . '/config/db.conf.php';
        $config = include $path;

        return $config[$dbConfName];
    }


}