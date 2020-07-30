<?php


use Controllers\ChatController;
use Helpers\ConfigHelper;
use Illuminate\Database\Capsule\Manager;
use Patterns\ChatFactory\Classes\GroupChat;

require __DIR__ . '/vendor/autoload.php';

$start = microtime(true);
$capsule = new Manager();

$config = ConfigHelper::getDbConfig('mobile_db');

$capsule->addConnection([
    'driver' => $config['driver'],
    'host' => '127.0.0.1:7771',
    'database' => 'indigo24Mobile',
    'username' => 'aibekq',
    'password' => '0VA_!8@x#R3bq',
    'charset' => $config['charset'],
    'prefix' => $config['prefix'],
    'collation' => $config['collation'],
]);
$capsule->setAsGlobal();

$groupId = 1293;

$group = Manager::table('groups')->find($groupId);
$members = Manager::table('group_members')->where('group_id', $groupId)->get();
$redis = new Redis();
$redis->pconnect("127.0.0.1", 6379);
$data = [];

$groupChatClass = new GroupChat();

$membersToArray = collect($members->pluck('customer_id')->toArray())
    ->map(function ($x) {
        return (array)$x;
    })
    ->flatten()
    ->toArray();


$config = ConfigHelper::getDbConfig('chat_db');
$capsule = new Manager();
$capsule->addConnection([
    'driver' => $config['driver'],
    'host' => $config['host'],
    'database' => $config['database'],
    'username' => $config['username'],
    'password' => $config['password'],
    'charset' => $config['charset'],
    'prefix' => $config['prefix'],
    'collation' => $config['collation'],
]);

$capsule->setAsGlobal();


$data['user_ids'] = implode(',',$membersToArray);
$data['user_id'] =1145;
$data['chat_name'] ="Пассив от электронного кошелька";
$data['type'] =ChatController::GROUP;


$groupChatClass->create($data, $redis);
dd(microtime(true) - $start);

















