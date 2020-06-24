<?php

namespace Controllers;

use Helpers\ResponseFormatHelper;
use Middlewars\Auth\CheckUserTokenMiddleware;
use Middlewars\Permission\CheckPrivilegesForAddGroupChat;
use Middlewars\Permission\CheckPrivilegesForMessageMiddleware;
use Middlewars\Permission\CheckUserInChatMembersMiddleware;

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
            //@TODO передать через ::class
            'middleware' => [CheckUserTokenMiddleware::class,CheckUserInChatMembersMiddleware::class],
        ],
        'message:write' => [
            'action' => 'MessageController@write',
            'params' => true,
        ],
        'message:edit' => [
            'action' => 'MessageController@edit',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class,CheckPrivilegesForMessageMiddleware::class]
        ],

        'message:deleted:all' => [
            'action' => 'MessageController@delete',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class,CheckPrivilegesForMessageMiddleware::class]
        ],
        'message:delete:self' =>[
            'action' => 'MessageController@deleteSelf',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class,CheckPrivilegesForMessageMiddleware::class]
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
            'middleware' => [CheckPrivilegesForAddGroupChat::class]
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
            $middlewars = $route['middleware'] ?? null;

            if (is_array($middlewars)) {
                foreach ($middlewars as $middlewar) {
                    $middlewareNamespace = $middlewar;
                    $middlewareClass = new $middlewareNamespace;
                    $middlewareResponse = $middlewareClass->handle($params);

                    if ($middlewareClass->isNext() !==false) {
                        continue;
                    }
                    return $middlewareResponse;
                }
            }

            return  $params ? (new $controller)->$method($params) : (new $controller)->$method();

        }

        return '404';
    }

    public function ping($data)
    {
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], ['ping']);

    }


}