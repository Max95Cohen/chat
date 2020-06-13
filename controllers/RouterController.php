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
        'user:writing' => [
            'action' => 'UserController@writing',
            'params' => true,
        ],
        'user:check:online' => [
            'action' => 'UserController@checkOnline',
            'params' => true,
        ],
        'user:writing' =>[
            'action' => 'UserController@writing',
            'params' => true,
        ],


        //ChatController
        'chat:create' => [
            'action' => 'ChatController@create',
            'params' => true,
        ],
        'chats:get' => [
            'action' => 'ChatController@getAll',
            'params' => true,
        ],
        'chat:get' => [
            'action' => 'ChatController@getOne',
            'params' => true,
        ],
        'chat:members' => [
            'action' => 'ChatController@getChatMembers',
            'params' => true,
        ],

        //messageController
        'message:create' => [
            'action' => 'MessageController@create',
            'params' => true,
            'middleware' => ['CheckUserTokenMiddleware'],
        ],
        'message:write' => [
            'action' => 'MessageController@write',
            'params' => true,
        ],
        // memberController

        'chat:members' => [
            'action' => 'MemberController@getChatMembers',
            'params' => true,
        ],
        'chat:members:privileges' =>[
            'action' => 'MemberController@changeUserPrivileges',
            'params' => true,
        ],
        'chat:members:delete' =>[
            'action' => 'MemberController@deleteMembers',
            'params' => true,
        ],
        'chat:members:add' =>[
            'action' => 'MemberController@addMembers',
            'params' => true,
        ],

        'chat:members:check' =>[
            'action' => 'MemberController@checkExists',
            'params' => true,
        ],

        //test
        'test:ping' => [
            'action' => 'RouterController@ping'
        ],



    ];


    public static function executeRoute($route, array $params = null, $fd = null,$server=null)
    {
        $route = self::$routes[$route] ?? null;

        if ($fd) {
            $params['connection_id'] = $fd;
        }
        if ($server){
            $params['server'] = $server;
        }


        $params['cmd'] = $route;

        if ($route) {
            $controllerAndMethod = explode('@', $route['action']);
            $controller = 'Controllers\\' . $controllerAndMethod[0];
            $method = $controllerAndMethod[1];
//            $middlewars = $route['middleware'] ?? null;


//            foreach ($middlewars as $middlewar) {
//                $middlewareClass = 'Middlewars\\' . $middlewar;
//                $middlewareAllow = $middlewareClass->handle($params);
//
//            }

            return  $params ? (new $controller)->$method($params) : (new $controller)->$method();

        }

        return '404';
    }

    public function ping($data)
    {
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], ['ping']);

    }


}