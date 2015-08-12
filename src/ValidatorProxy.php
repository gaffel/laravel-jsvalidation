<?php

namespace Proengsoft\JsValidation;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;

class ValidatorProxy implements ValidatorContract
{
    /**
     * Registered validator instance.
     *
     * @var \Illuminate\Contracts\Validation\Validator
     */
    protected $validator;

    /**
     * ValidatorProxy constructor.
     * @param ValidatorContract $validator
     */
    public function __construct(ValidatorContract $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Get the messages for the instance.
     *
     * @return \Illuminate\Support\MessageBag
     */
    public function getMessageBag()
    {
        return $this->validator->getMessageBag();
    }

    /**
     * Determine if the data fails the validation rules.
     *
     * @return bool
     */
    public function fails()
    {
        return $this->validator->fails();
    }

    /**
     * Get the failed validation rules.
     *
     * @return array
     */
    public function failed()
    {
        return $this->validator->failed();
    }

    /**
     * Add conditions to a given field based on a Closure.
     *
     * @param  string $attribute
     * @param  string|array $rules
     * @param  callable $callback
     * @return void
     */
    public function sometimes($attribute, $rules, callable $callback)
    {
        $this->validator->sometimes($attribute, $rules, $callback);
    }

    /**
     * After an after validation callback.
     *
     * @param  callable|string $callback
     * @return $this
     */
    public function after($callback)
    {
        return $this->validator->after($callback);
    }

    public function validationData(){

        $jsMessages = array();
        $va=new JavascriptValidation($this->validator);
        return $va->validations();

    }


    /**
     * is triggered when invoking inaccessible methods in an object context.
     *
     * @param $name string
     * @param $arguments array
     * @return mixed
     * @link http://php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.methods
     */
    function __call($name, $arguments)
    {
        return call_user_func_array(array($this->validator, $name), $arguments);
    }


}