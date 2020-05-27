<?php

namespace Controllers;

use Helpers\ResponseFormatHelper;

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
        'chat:get' =>[
            'action' => 'ChatController@getOne',
            'params'=> true,
        ],

        //messageController
        'message:create' => [
            'action' => 'MessageController@create',
            'params' => true,
        ],
        'test:ping' => [
            'action' => 'RouterController@ping'
        ]



    ];


    public static function executeRoute($route, array $params = null, $fd = null)
    {
        $route = self::$routes[$route] ?? null;

        if ($fd) {
            $params['connection_id'] = $fd;
        }

        $params['cmd'] = $route;

        if ($route) {
            $controllerAndMethod = explode('@', $route['action']);
            $controller = 'Controllers\\' . $controllerAndMethod[0];
            $method = $controllerAndMethod[1];
            return $params ? call_user_func([$controller, $method], $params) : call_user_func([$controller, $method]);
        }

        return '404';
    }

    public function ping($data)
    {
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],['ping']);

    }


}