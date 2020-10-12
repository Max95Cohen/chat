<?php


namespace Validation;


use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class UserCheckValidation implements BaseValidationInterface
{
    /**
     * @param array $params
     * @return array|bool|mixed
     */
    public function validate(array $params)
    {
        $v = new Validator($params, null, 'ru');

        $v->rule('required', 'user_id');
        $v->rule('integer', 'user_id');
        $v->rule('required', 'userToken');
        $v->rule('required', 'phone');
        $v->rule('regex', 'phone', '#^\+\d{5,30}$|^\d{5,30}$#');

        $v->validate();

        return $v->errors();

    }
}
