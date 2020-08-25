<?php


namespace Validation;


use Helpers\GetResponseForMessageType;
use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class ChatMessageByTypeValidation implements BaseValidationInterface
{

    public function validate(array $params)
    {
        $v = new Validator($params,null,'ru');

        $v->rule("required",'user_id');
        $v->rule("integer",'user_id');

        $v->rule('required','userToken');

        $v->rule('required','chat_id');
        $v->rule('integer','chat_id');

        $v->rule('required','type');
        $v->rule('in','type',GetResponseForMessageType::MESSAGE_TYPES);

        $v->validate();

        return $v->errors();
    }
}