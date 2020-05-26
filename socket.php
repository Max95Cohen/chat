<?php

use Controllers\RouterController;

require __DIR__ . '/vendor/autoload.php';

$server = new swoole_websocket_server("127.0.0.1", 9502);


$capsule = new Illuminate\Database\Capsule\Manager();


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

$capsule->setAsGlobal();

$server->on('open', function ($server, $req) {

    return $req->fd;

});

$server->on('message', function ($server, $frame) {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);


    $requestData = (json_decode($frame->data, true));

    $responseData = RouterController::executeRoute($requestData['cmd'], $requestData['data'], $frame->fd);


    $notifyUsers = $responseData['notify_users'];
    $response = $responseData['data'];

    foreach ($notifyUsers as $notifyUser) {
        $connectionId = $redis->get("con:$notifyUser");
        if ($connectionId) {
            $server->push($connectionId, json_encode($response,JSON_UNESCAPED_UNICODE));
        }
    }

});


$server->on('close', function ($server, $fd) {
    echo "connection close: {$fd}\n";
});


$server->start();


