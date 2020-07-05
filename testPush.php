<?php

use Kreait\Firebase\Messaging\Notification;

require __DIR__ . '/vendor/autoload.php';


//$redis = new Redis();
//$redis->connect('127.0.0.1',6379);
//$usersPhones = $redis->zRange("users:phones",0,100,true);
//
//foreach ($usersPhones as $id => $phone) {
//    dump($id);
////    $redis->zAdd("chat:members:17",['NX'],\Controllers\ChatController::SUBSCRIBER,$id);
//    $redis->zRemRangeByScore('chat:members:17',1,3);
//}
//dd("end");
//
//
//
//
//
//
//
//
//
//$factory = (new \Kreait\Firebase\Factory())->withServiceAccount(__DIR__ . '/indigo24-fdf1b-firebase-adminsdk-upaae-5fb0aaf3a5.json');
//
//
//
//
//
//$FCMTokenRedmi  ='fJAEcrRFTpW11fT72RNHxG:APA91bEHnCpc827laAU5i1LZ-ptrTHURzHCLctDO2K7o7P-jnJgs2EKu4V8Qp_Vw9b5Vsg8Yy0s2LSesy5o7_evfixNAc_-49YjhHYhYqffDHogku8Wh1LLdakY4Euik_FIUayfMnCl-';
//$FCMTokenTestPhone = 'eAWCasCASN2tCYBXE9QrT4:APA91bHvkxgD0_Qo3OreohoCEmIpUaBOq4yMplW3qUD1rgJnoy9fL2sojKiCrty6qW5UKqRmbzLMePWHTYfUgJVedG51OpQYcdyJAc4U_rvpjzpqDIotFZzADoIVRF3Kz6KuQPmpnYLE';
//
//
//$deviceTokens = [$FCMTokenRedmi,$FCMTokenTestPhone];
//
//
//
//$messaging = $factory->createMessaging();
//
//
//$message = \Kreait\Firebase\Messaging\CloudMessage::new()
//    ->withNotification(Notification::create('тестовый тайтл', 'Тестовое тело сообщения'))
//    ->withData(['key' => 'value']);;
//
//
//$sendReport = $messaging->sendMulticast($message,$deviceTokens);
//
//echo 'Successful sends: '.$sendReport->successes()->count().PHP_EOL;
//echo 'Failed sends: '.$sendReport->failures()->count().PHP_EOL;
//
//
//dd(1);



$FCMToken = 'cv4yVFqKRummWRgzHr5dd1:APA91bEM9se7KU1SUWci3r8aiALcTAhMmSHgfo2igXsYQqo_A_4B3PQGhpw0agKL223jyuCY_Fx8nKCHypQFnxOHAwaDbKupDQ-62Jy9lnrLNos8lUeEYYnyTzUOk9VpRqhGK7HXYnlj';

$postData = json_encode([
    'collapse_key' => 'type_a',
    'data' => [
        'body' => "тестовое сообщение",
    ],
    'priority' => 'high',
    'to' => $FCMToken,
    'notification' => [
      'body' => "тестовое сообщение",
      'title' => 'тестовый тайтл'
    ],
]);


$fireBaseUrl = 'https://fcm.googleapis.com/fcm/send';
$fireBaseApiKey  = 'AAAAvEEv5W0:APA91bG0qVzveg4pOsnyf6jm3Jj5NuA3_Q37hBF_rqYX59zAtUQpF1qMUSgKIdgs9JRBwkdZ58vBhCF_DEhNSE_OOn1-oox0Zos6cxsi5wxR22CqPvrqxHbTMg0nLo6AlZZoHhCc7J4w';

$ch = curl_init();
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
$result = curl_exec($ch);

dd($result);

curl_close($ch);