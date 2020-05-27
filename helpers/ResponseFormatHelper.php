<?php


namespace Helpers;


class ResponseFormatHelper
{
    /**
     * @param array $notifyUsers
     * @param array $data
     * @return array[]
     */
    public static function successResponseInCorrectFormat(array $notifyUsers, array $data)
    {
        return [
            'cmd' =>$data['cmd'],
            'notify_users' => $notifyUsers,
            'data' => $data,
        ];
    }



}