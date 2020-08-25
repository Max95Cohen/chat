<?php


namespace Validation;


use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class ChatsGetValidation implements BaseValidationInterface
{

    public function validate(array $params)
    {
        $v = new Validator($params,null,'ru');

        $v->rule("required",'user_id');
        $v->rule("integer",'user_id');

        $v->rule('required','userToken');

        $v->validate();

        return $v->errors();
    }
}