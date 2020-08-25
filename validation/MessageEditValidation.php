<?php


namespace Validation;


use Helpers\MessageHelper;
use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class MessageEditValidation implements BaseValidationInterface
{

    /**
     * @param array $params
     * @return mixed|void
     */
    public function validate(array $params)
    {
        $v = new Validator($params,null,'ru');

        $v->rule("required",'user_id');
        $v->rule("integer",'user_id');

        $v->rule("required",'chat_id');
        $v->rule("integer",'chat_id');

        $v->rule('required','message_type');
        $v->rule('in',MessageHelper::getMessageTypes());

        $v->rule('required','message_id');
        $v->rule("regex",'message_id','#^message:\d{1,}:\d{1,}$#')->message("Поле message_id не в формате message:{user_id}:{int}");

        $v->validate();


    }
}