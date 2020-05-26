<?php


namespace Controllers;


use Helpers\PhoneHelper;

class UserController
{

    public function checkExist(array $data)
    {
        $phone = $data['phone'];

        $redis = new \Redis();
        $redis->connect('127.0.0.1',6379);
        $phone = PhoneHelper::replaceForSeven($phone);

        $checkExist = $redis->zRangeByScore('users:phones',$phone,$phone);

        if (count($checkExist)) {
            return [
              'success' => true
            ];
        }

        return  [
          'success' => false,
        ];

    }


}