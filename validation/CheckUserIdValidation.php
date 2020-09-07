<?php


namespace Validation;


use Traits\RedisTrait;
use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class CheckUserIdValidation implements BaseValidationInterface
{
    use RedisTrait;

    /**
     * @param array $params
     * @return mixed|void
     */
    public function validate(array $params)
    {
        $checkUserId = $params['check_user_id'] ?? null;

        if ($checkUserId){
            $checkExist = $this->redis->get("userId:phone:{$params['check_user_id']}");
        }

        if ($checkExist) {
            $params['check_user_exist'] = $checkExist;
        }

        $v = new Validator($params, null, 'ru');

        $v->rule("required", 'user_id');
        $v->rule("integer", 'user_id');

        $v->rule("required", 'check_user_exist')->message("Пользователь не найден");

        $v->rule("required", 'userToken');

        $v->rule("required", 'check_user_id');
        $v->rule("integer", 'check_user_id');

        $v->validate();
        return $v->errors();

    }
}