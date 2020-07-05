<?php

use Helpers\ConfigHelper;
use Illuminate\Database\Capsule\Manager;

require __DIR__ . '/vendor/autoload.php';




$capsule = new Manager();

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

$capsule->setAsGlobal();



while (true) {
    $allNotify = $redis->zRange("all:notify:queue", 0, -1);

    foreach ($allNotify as $notify) {
        
    }


}


$notifies = [];

$fcmTokens = ['cv4yVFqKRummWRgzHr5dd1:APA91bEM9se7KU1SUWci3r8aiALcTAhMmSHgfo2igXsYQqo_A_4B3PQGhpw0agKL223jyuCY_Fx8nKCHypQFnxOHAwaDbKupDQ-62Jy9lnrLNos8lUeEYYnyTzUOk9VpRqhGK7HXYnlj'];
$fireBaseUrl = 'https://fcm.googleapis.com/fcm/send';
$fireBaseApiKey  = 'AAAAvEEv5W0:APA91bG0qVzveg4pOsnyf6jm3Jj5NuA3_Q37hBF_rqYX59zAtUQpF1qMUSgKIdgs9JRBwkdZ58vBhCF_DEhNSE_OOn1-oox0Zos6cxsi5wxR22CqPvrqxHbTMg0nLo6AlZZoHhCc7J4w';


$mh = curl_multi_init();
foreach ($fcmTokens as $k => $fcmToken) {
    $ch = curl_init();

    $postData = json_encode([
        'collapse_key' => 'type_a',
        'data' => [
            'body' => "я просто проверяю пушки можете проигнорировать это",
        ],
        'priority' => 'high',
        'to' => $fcmToken,
        'notification' => [
            'body' => "тест пушки да",
            'title' => 'тест пушки да'
        ],
    ]);

    curl_setopt($ch, CURLOPT_URL,$fireBaseUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: key=' . $fireBaseApiKey,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


    $notifies[$k] = $ch;

    curl_multi_add_handle($mh, $notifies[$k]);
}
$active = null;

do{
    curl_multi_exec($mh,$active);
}while($active);



foreach(array_keys($notifies) as $key){
    $error = curl_error($notifies[$key]);
    $requestUri = curl_getinfo($notifies[$key], CURLINFO_EFFECTIVE_URL);
    $time = curl_getinfo($notifies[$key], CURLINFO_TOTAL_TIME);
    $response = curl_multi_getcontent($notifies[$key]);  // get results
    if (!empty($error)) {
        echo "The request $key return a error: $error" . "\n";
    }
    else {
        echo "The request to '$requestUri' returned '$response' in $time seconds." . "\n";
    }

    curl_multi_remove_handle($mh, $notifies[$key]);
}

// close current handler
curl_multi_close($mh);