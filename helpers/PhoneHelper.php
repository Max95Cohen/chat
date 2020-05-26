<?php

namespace Helpers;

class PhoneHelper
{
    public static function replaceForSeven(string $phone)
    {
        if ($phone[0] == '8' && mb_strlen($phone) == 11) {
            $phone =  '7'.substr($phone,1);
        }
        if (substr($phone,0,2) == '+7' && mb_strlen($phone) == 11) {
            $phone =  '7'.substr($phone,2);
        }

        return $phone;
    }

}