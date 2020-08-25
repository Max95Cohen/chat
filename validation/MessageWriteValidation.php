<?php

namespace Validation;

use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class MessageWriteValidation implements BaseValidationInterface
{

    public function validate(array $params)
    {

        $params['message_id'] = $params['message_id'] ?? 'null';

        $v = new Validator($params);
        $v->rule("required",'message_id')->message("поле message_id обязательное");
        $v->rule("required",'chat_id')->message("Поле chat_id обязательное");
        $v->rule("integer",'user_id')->message("Поле user_id должно быть int");
        $v->rule("integer",'chat_id')->message("Поле chat_id должно быть int");

        $v->rule("regex",'message_id','#^message:\d{1,}:\d{1,}$#')->message("Поле message_id не в формате message:{user_id}:{int} я получил {$params['message_id']}");

        $v->validate();
        return $v->errors();

    }



}