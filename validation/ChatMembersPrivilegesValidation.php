<?php


namespace Validation;


use Controllers\ChatController;
use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class ChatMembersPrivilegesValidation implements BaseValidationInterface
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

        $v->rule('required','role');
        $v->rule('in','role',ChatController::getRolesForOwner());

        $v->rule('regex','members','#\d{1,20}|\,\d{1,20}#');

        $v->validate();

        return $v->errors();
    }
}