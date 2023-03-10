<?php

namespace Controllers;

use Helpers\Helper;
use Helpers\ResponseFormatHelper;
use Middlewars\Auth\CheckUserTokenMiddleware;
use Middlewars\Exists\CheckChatExistMiddleware;
use Middlewars\Permission\CheckPrivilegesForAddGroupChat;
use Middlewars\Permission\CheckPrivilegesForDeleteMemberMiddleware;
use Middlewars\Permission\CheckPrivilegesForMessageMiddleware;
use Middlewars\Permission\CheckUserInChatMembersMiddleware;
use Middlewars\Validation\ValidationMiddleware;

class RouterController
{
    public static $routes = [
        'init' => [
            'action' => 'AuthController@init',
            'params' => true,
//            'middleware' => [CheckUserTokenMiddleware::class],
        ],
        'user:check' => [
            'action' => 'UserController@checkExist',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class],
        ],
        'user:writing' => [
            'action' => 'UserController@writing',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'user:check:online' => [
            'action' => 'UserController@checkOnline',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class],
        ],

        //ChatController
        'chat:create' => [
            'action' => 'ChatController@create',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class],
        ],
        'chats:get' => [
            'action' => 'ChatController@getAll',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class],
        ],
        'chat:get' => [
            'action' => 'ChatController@getOne',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        # TODO need?
        /*'chat:members' => [
            'action' => 'ChatController@getChatMembers',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class],
        ],*/

        'chat:change:name' => [
            'action' => 'ChatController@changeChatName',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],

        'chat:mute' => [
            'action' => 'ChatController@muteChat',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],

        'chat:delete' => [
            'action' => 'MemberController@deleteChat',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class],
        ],

        //messageController
        'message:create' => [
            'action' => 'MessageController@create',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'message:write' => [ # TODO remove in new version. Incorrect typing.
            'action' => 'MessageController@write',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'message:read' => [
            'action' => 'MessageController@read',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],

        'message:edit' => [
            'action' => 'MessageController@edit',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckPrivilegesForMessageMiddleware::class]
        ],

        'message:deleted:all' => [
            'action' => 'MessageController@delete',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckPrivilegesForMessageMiddleware::class]
        ],
        'message:delete:self' => [
            'action' => 'MessageController@deleteSelf',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckPrivilegesForMessageMiddleware::class]
        ],

        'message:forward' => [
            'action' => 'MessageController@forward',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class]
        ],

        // memberController
        'chat:members' => [
            'action' => 'MemberController@getChatMembers',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'chat:members:privileges' => [
            'action' => 'MemberController@changeUserPrivileges',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'chat:members:delete' => [
            'action' => 'MemberController@deleteMembers',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckPrivilegesForDeleteMemberMiddleware::class]
        ],
        'chat:members:add' => [
            'action' => 'MemberController@addMembers',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckPrivilegesForAddGroupChat::class]
        ],
        'chat:members:check' => [
            'action' => 'MemberController@checkExists',
            'params' => true,
        ],
        'chat:member:leave' => [
            'action' => 'MemberController@chatLeave',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'chat:member:search' => [
            'action' => 'MemberController@searchInChat',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckChatExistMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'chat:message:by:type' => [
            'action' => 'MessageController@getChatMessageByType',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckChatExistMiddleware::class, CheckUserInChatMembersMiddleware::class],
        ],
        'chat:stickers' => [
            'action' => 'StickerController@getAll',
            'params' => true,
        ],
        'chat:pinned' => [
            'action' => 'ChatController@pinned',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class],
        ],
        'user:settings:get' => [
            'action' => 'SettingsController@getSettings',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class],
        ],
        'user:settings:set' => [
            'action' => 'SettingsController@setSettings',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class],
        ],
        'set:group:avatar' => [
            'action' => 'ChatController@setChatAvatar',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class, CheckUserInChatMembersMiddleware::class, CheckPrivilegesForAddGroupChat::class],
        ],

        //ContactController
        'contact:save' => [
            'action' => 'ContactController@save',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class],
        ],
        'contact:get' => [
            'action' => 'ContactController@get',
            'params' => true,
            'middleware' => [CheckUserTokenMiddleware::class],
        ],
        'check:user:id' => [
            'action' => 'UserController@checkUserById',
            'params' => true,
            'middleware' => [ValidationMiddleware::class, CheckUserTokenMiddleware::class],
        ],
        /*'chat:pinned' => [
            'action' => 'ChatController@pinned',
            'params' => true,
//            'middleware' => [CheckUserTokenMiddleware::class]
        ],*/

        //test
        'test:ping' => [
            'action' => 'RouterController@ping'
        ],
    ];

    public static function executeRoute($route, array $params = null, $fd = null, $server = null)
    {
        $cmdName = $route;

        $route = self::$routes[$route] ?? null;

        if ($fd) {
            $params['connection_id'] = $fd;
        }

        if ($server) {
            $params['server'] = $server;
        }

        $params['cmd'] = $route;
        $params['cmd_name'] = $cmdName;

//        echo "PARAMS\n"; # TODO remove;
//        Helper::log($params); # TODO remove;

        if ($route) {
            $controllerAndMethod = explode('@', $route['action']);
            $controller = 'Controllers\\' . $controllerAndMethod[0];
            $method = $controllerAndMethod[1];
            $middlewares = $route['middleware'] ?? null;

            if (is_array($middlewares)) {
                foreach ($middlewares as $middleware) {
                    $middlewareNamespace = $middleware;
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
