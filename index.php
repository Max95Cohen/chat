<?php

use Controllers\ChatController;
use Controllers\MemberController;
use Controllers\MessageController;
use Controllers\RouterController;

require __DIR__ . '/vendor/autoload.php';
$r = new Redis();
$r->connect('127.0.0.1', 6379);


//RouterController::executeRoute('/chat/store','123');

$messageController = new MessageController();
$chatContoller = new ChatController();
//$memberController = new MemberController();

//$chatContoller->getUserChats('123');
//$messageController->store('123');

$data = [
    'per_page' => 50,
    'page' => 1,
    'chat_id' => 7,
    'unic' => 'testUserUnic',
    'message_id' => 16,
];


//$messageController->destroy($data);
$messageController->destroyForAll($data);






