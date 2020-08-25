<?php


namespace Validation;


use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class UserCheckOnlineValidation implements BaseValidationInterface
{

    public function validate(array $params)
    {
        $v = new Validator($params,null,'ru');

        $v->rule("required",'user_id');
        $v->rule("integer",'user_id');

        $v->rule("required",'user_ids');
        $v->rule("regex",'user_ids','#^message:\d{1,}:\d{1,}$#')->message("Поле message_id не в формате message:{user_id}:{int} я получил {$params['message_id']}");

        $v->rule('required','userToken');

        $v->validate();

        return $v->errors();
    }
}