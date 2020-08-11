<?php

namespace Controllers;

use Helpers\ResponseFormatHelper;
use Middlewars\Auth\CheckUserTokenMiddleware;
use Middlewars\Exists\CheckChatExistMiddleware;
use Middlewars\Permission\CheckPrivilegesForAddGroupChat;
use Middlewars\Permission\CheckPrivilegesForDeleteMemberMiddleware;
use Middlewars\Permission\CheckPrivilegesForMessageMiddleware;
use Middlewars\Permission\CheckUserInChatMembersMiddleware;

class RouterController
{

    public static $routes = [
        'init' => [
            'action' => 'AuthController@init',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class],
        ],

        'user:check' => [
            'action' => 'UserController@checkExist',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class],
        ],
        'user:writing' => [
            'action' => 'UserController@writing',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'user:check:online' => [
            'action' => 'UserController@checkOnline',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class],
        ],

        //ChatController
        'chat:create' => [
            'action' => 'ChatController@create',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class],
        ],
        'chats:get' => [
            'action' => 'ChatController@getAll',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class],
        ],
        'chat:get' => [
            'action' => 'ChatController@getOne',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'chat:members' => [
            'action' => 'ChatController@getChatMembers',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class],
        ],

        'chat:change:name' => [
            'action' => 'ChatController@changeChatName',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],

        'chat:mute' => [
            'action' => 'ChatController@muteChat',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],

        'chat:delete' =>[
            'action' => 'MemberController@deleteChat',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],


        //messageController
        'message:create' => [
            'action' => 'MessageController@create',
            'params' => true,
            //@TODO передать через ::class
            'middleware' => [CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'message:write' => [
            'action' => 'MessageController@write',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'message:edit' => [
            'action' => 'MessageController@edit',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class, CheckPrivilegesForMessageMiddleware::class]
        ],

        'message:deleted:all' => [
            'action' => 'MessageController@delete',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class, CheckPrivilegesForMessageMiddleware::class]
        ],
        'message:delete:self' => [
            'action' => 'MessageController@deleteSelf',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class, CheckPrivilegesForMessageMiddleware::class]
        ],

        'message:forward' => [
            'action' => 'MessageController@forward',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class]
        ],


        // memberController

        'chat:members' => [
            'action' => 'MemberController@getChatMembers',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'chat:members:privileges' => [
            'action' => 'MemberController@changeUserPrivileges',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'chat:members:delete' => [
            'action' => 'MemberController@deleteMembers',
            'params' => true,
            'middleware' => [CheckPrivilegesForDeleteMemberMiddleware::class]
        ],
        'chat:members:add' => [
            'action' => 'MemberController@addMembers',
            'params' => true,
            'middleware' => [CheckPrivilegesForAddGroupChat::class]
        ],

        'chat:members:check' => [
            'action' => 'MemberController@checkExists',
            'params' => true,
        ],
        'chat:member:leave' => [
            'action' => 'MemberController@chatLeave',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'chat:member:search' => [
            'action' => 'MemberController@searchInChat',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class, CheckChatExistMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],


        //test
        'test:ping' => [
            'action' => 'RouterController@ping'
        ],


    ];


    public static function executeRoute($route, array $params = null, $fd = null, $server = null)
    {
        $route = self::$routes[$route] ?? null;

        if ($fd) {
            $params['connection_id'] = $fd;
        }
        if ($server) {
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
                    if ($middlewareClass->isNext() !== false) {
                        continue;
                    }
                    return $middlewareResponse;
                }
            }

            return $params ? (new $controller)->$method($params) : (new $controller)->$method();

        }

        return '404';
    }

    public function ping($data)
    {
        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']], ['ping']);

    }


}