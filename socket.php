<?php

require __DIR__ . '/vendor/autoload.php';

use Carbon\Carbon;
use Controllers\RouterController;
use Helpers\ConfigHelper;
use Helpers\Helper;
use Illuminate\Database\Capsule\Manager;

$server = new swoole_websocket_server('0.0.0.0', 9502, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);

$capsule = new Illuminate\Database\Capsule\Manager();

require_once 'env.php';

$config = ConfigHelper::getDbConfig('chat_db');

$capsule->addConnection([
    'driver' => $config['driver'],
    'host' => $config['host'],
    'database' => $config['database'],
    'username' => $config['username'],
    'password' => $config['password'],
    'charset' => $config['charset'],
    'collation' => $config['collation'],
    'prefix' => $config['prefix'],
]);

$server->set([
    'ssl_cert_file' => Helper::get('CERT_FILE'),
    'ssl_key_file' => Helper::get('KEY_FILE'),
]);

$capsule->setAsGlobal();

Carbon::setLocale('ru');

$server->on('open', function ($server, $req) {
    echo "OPEN {$req->fd}\n"; # TODO remove;

    return $req->fd;
});

$server->on('message', function ($server, $frame) {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);

    $requestData = json_decode($frame->data, true);

    Helper::log($requestData, 'REQUEST'); # TODO remove;

    $requestData['data']['server'] = $server;

    $responseData = RouterController::executeRoute($requestData['cmd'], $requestData['data'], $frame->fd);

    Helper::log($responseData, 'RESPONSE'); # TODO remove;

    $notifyUsers = $responseData['notify_users'];
    $response = [];
    $response['cmd'] = $requestData['cmd'];
    $response['data'] = $responseData['data'];

    $logout = $response['data']['logout'] ?? null;

    if ($logout) {
        $response['logout'] = true;
        $server->push($frame->fd, json_encode($response, JSON_UNESCAPED_UNICODE));
    } else {
        foreach ($notifyUsers as $notifyUser) {
            $connectionId = intval($redis->get("con:{$notifyUser}"));
            $checkConnection = $redis->zRangeByScore('users:connections', $connectionId, $connectionId);

            if ($checkConnection) {
                $server->push($connectionId, json_encode($response, JSON_UNESCAPED_UNICODE));
            }
        }
    }
});

$server->on('close', function ($server, $fd) {
    echo "CLOSE {$fd}\n"; # TODO comment;

    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);

    $getUserId = $redis->zRangeByScore('users:connections', $fd, $fd);
    $userId = array_shift($getUserId);

    $redis->zRemRangeByScore('users:connections', $fd, $fd);
    $redis->set("user:last:visit:{$userId}", time());

    $redis->close();
});

$server->start();
