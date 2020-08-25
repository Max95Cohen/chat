<?php


namespace Validation;


use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class UserWritingValidation implements BaseValidationInterface
{

    /**
     * @param array $params
     * @return array|bool|mixed
     */
    public function validate(array $params)
    {
        $v = new Validator($params,null,'ru');

        $v->rule("required",'user_id');
        $v->rule("integer",'user_id');

        $v->rule("required",'chat_id');
        $v->rule("integer",'chat_id');

        $v->rule('required','userToken');

        $v->validate();

        return $v->errors();
    }
}