<?php


namespace Validation;


use Validation\Interfaces\BaseValidationInterface;
use Valitron\Validator;

class SaveContactValidation implements BaseValidationInterface
{

    public function validate(array $params)
    {
        $v = new Validator($params);

        $v->rule('required',[
            'user_id',
            'userToken',
            'phone'
        ]);

        $v->rule("regex",'phone','#^\+{0,1}\d{10,25}$#')->message("phone в неправильном формате");

        $v->validate();
        return $v->errors();


    }
}