<?php

use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';

Carbon::setLocale('ru');
$date = date('Y-m-d H:i:s',1510821262);
$messageCreatedDate = new Carbon($date);
$replacmentString =$messageCreatedDate->diffForHumans(Carbon::now());

dd(preg_replace('#до#','назад',$replacmentString));