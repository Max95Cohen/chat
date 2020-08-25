<?php


namespace Validation;


use Controllers\ChatController;
use Helpers\MessageHelper;
use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class MessageCreateValidation implements BaseValidationInterface
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

        $v->rule("required",'userToken');

        $v->rule("required",'chat_id');
        $v->rule("integer",'chat_id');

        $v->rule("required",'user_ids');

        $v->rule('in','message_type',MessageHelper::getMessageTypes());



        $v->rule("required",'phone');
        $v->rule("regex",'phone','#^\+\d{5,30}$|^\d{5,30}$#');

        $v->rule("type",'in',[
            ChatController::PRIVATE,
            ChatController::GROUP
        ]);

        $v->validate();

        return $v->errors();
    }
}