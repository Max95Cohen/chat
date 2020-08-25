<?php


namespace Validation;


use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class InitValidation implements BaseValidationInterface
{

    public function validate(array $data)
    {
        $params['message_id'] = $params['message_id'] ?? 'null';

        $v = new Validator($params,null,'ru');

        $v->rule("required",'user_id');
        $v->rule("integer",'user_id');
        $v->rule("required",'userToken');

        $v->rule("required",'chat_id');
        $v->rule("integer",'chat_id');



        $v->validate();
        return $v->errors();


    }
}