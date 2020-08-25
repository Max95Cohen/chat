<?php


namespace Validation;


use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class MessageDeleteSelfValidation implements BaseValidationInterface
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

        $v->rule('required','userToken');

        $v->rule("regex",'message_id','#^message:\d{1,}:\d{1,}$#')->message("Поле message_id не в формате message:{user_id}:{int}");

        $v->validate();

        return $v->errors();
    }
}