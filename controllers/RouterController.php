<?php

namespace Controllers;

class RouterController
{

    public static $routes = [
        'chat:create' => [
            'action' => 'ChatController@store',
            'params' => true,
        ],
    ];


    public static function executeRoute($route,string $params=null)
    {
        $route = self::$routes[$route] ?? null;

        if ($route){
            $controllerAndMethod = explode('@', $route['action']);
            $controller = 'Controllers\\' . $controllerAndMethod[0];
            $method = $controllerAndMethod[1];
            return $params ? call_user_func([$controller, $method],$params) : call_user_func([$controller, $method]);
        }

        return '404';
    }


}