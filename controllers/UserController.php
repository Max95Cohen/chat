<?php


namespace Controllers;


use Helpers\PhoneHelper;
use Helpers\ResponseFormatHelper;

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
            return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],[
                'status' => 'true',
                'phone' =>$data['phone']
            ]);
        }

        return ResponseFormatHelper::successResponseInCorrectFormat([$data['user_id']],[
            'status' => 'false',
            'phone' =>$data['phone']
        ]);

    }


}