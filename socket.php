<?php

use Carbon\Carbon;
use Controllers\RouterController;

require __DIR__ . '/vendor/autoload.php';

//$server = new swoole_websocket_server("0.0.0.0", 9502, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
$server = new swoole_websocket_server("127.0.0.1", 9502);


$capsule = new Illuminate\Database\Capsule\Manager();
const MEDIA_URL = 'http://media.loc/';

$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'chat',
    'username' => 'admin',
    'password' => '123',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);

//$server->set([
//    'ssl_cert_file' => "/etc/letsencrypt/live/oyhdo.com/fullchain.pem",
//    'ssl_key_file'  => "/etc/letsencrypt/live/oyhdo.com/privkey.pem"
//]);

$capsule->setAsGlobal();


Carbon::setLocale('ru');

$server->on('open', function ($server, $req) {

    return $req->fd;

});

$server->on('message', function ($server, $frame) {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);

    $requestData = (json_decode($frame->data, true));

    $requestData['data']['server'] = $server;

    $responseData = RouterController::executeRoute($requestData['cmd'], $requestData['data'], $frame->fd);

    $notifyUsers = $responseData['notify_users'];
    $response = [];
    $response['cmd'] = $requestData['cmd'];
    $response['data'] = $responseData['data'];

    foreach ($notifyUsers as $notifyUser) {
        $connectionId = intval($redis->get("con:$notifyUser"));
        $checkConnection = $redis->zRangeByScore('users:connections', $connectionId, $connectionId);
        if ($checkConnection) {
            $server->push($connectionId, json_encode($response, JSON_UNESCAPED_UNICODE));
        }

    }

});


$server->on('close', function ($server, $fd) {
    echo "connection close: {$fd}\n";
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);

    $getUserId = $redis->zRangeByScore('users:connections', $fd, $fd);
    $userId = array_shift($getUserId);

    $redis->zRemRangeByScore('users:connections', $fd, $fd);
    $redis->set("user:last:visit:{$userId}", time());


    $redis->close();

});


$server->start();


