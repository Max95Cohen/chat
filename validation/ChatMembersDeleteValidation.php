<?php


namespace Validation;


use Controllers\ChatController;
use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class ChatMembersDeleteValidation implements BaseValidationInterface
{

    public function validate(array $params)
    {
        $v = new Validator($params,null,'ru');

        $v->rule("required",'user_id');
        $v->rule("integer",'user_id');

        $v->rule("required",'chat_id');
        $v->rule("integer",'chat_id');

        $v->rule('required','userToken');

        $v->rule('required','member_id');
        $v->rule('integer','member_id');

        $v->validate();

        return $v->errors();
    }
}