<?php

use Controllers\RouterController;

require __DIR__ . '/vendor/autoload.php';

$server = new swoole_websocket_server("127.0.0.1", 9502);

$server->on('open', function($server, $req) {

    return $req->fd;


});

$server->on('message', function($server, $frame) {
    echo "received message: {$frame->data}\n";

    var_dump($frame);

    $requestData = (json_decode($frame->data,true));

    $responseData = RouterController::executeRoute('init',$requestData['data'],$frame->fd);

    $server->push($frame->fd, json_encode($responseData));
});


$server->on('close', function($server, $fd) {
    echo "connection close: {$fd}\n";
});

$server->start();
