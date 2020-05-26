<?php

namespace Controllers;

class RouterController
{

    public static $routes = [
        'init' => [
            'action' => 'AuthController@init',
            'params' => true,
        ],
        'user:check' => [
            'action' => 'UserController@checkExist',
            'params' => true,
        ],

        //ChatController
        'chat:create' => [
            'action' => 'ChatController@create',
            'params' => true,
        ],
        'chats:get' => [
            'action' => 'ChatController@getAll',
            'params'=> true,
        ],

        //messageController
        'message:create' => [
            'action' => 'MessageController@create',
            'params' => true,
        ],


    ];


    public static function executeRoute($route, array $params = null, $fd = null)
    {
        $route = self::$routes[$route] ?? null;

        if ($fd) {
            $params['connection_id'] = $fd;
        }

        if ($route) {
            $controllerAndMethod = explode('@', $route['action']);
            $controller = 'Controllers\\' . $controllerAndMethod[0];
            $method = $controllerAndMethod[1];
            return $params ? call_user_func([$controller, $method], $params) : call_user_func([$controller, $method]);
        }

        return '404';
    }


}