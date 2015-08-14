<?php

namespace Proengsoft\JsValidation;

use Illuminate\Validation\Factory;

class ValidatorFactory extends Factory
{

    /**
     * Create a new Validator instance.
     *
     * @param  array  $data
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     * @return \Illuminate\Validation\Validator
     */
    public function make(array $data, array $rules, array $messages = array(), array $customAttributes = array())
    {

        $validator = parent::make($data, $rules, $messages, $customAttributes);
        return new ValidatorProxy($validator);
    }

}