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
        // The presence verifier is responsible for checking the unique and exists data
        // for the validator. It is behind an interface so that multiple versions of
        // it may be written besides database. We'll inject it into the validator.
        $validator = $this->resolve($data, $rules, $messages, $customAttributes);

        if ( ! is_null($this->verifier))
        {
            $validator->setPresenceVerifier($this->verifier);
        }

        // Next we'll set the IoC container instance of the validator, which is used to
        // resolve out class based validator extensions. If it is not set then these
        // types of extensions will not be possible on these validation instances.
        if ( ! is_null($this->container))
        {
            $validator->setContainer($this->container);
        }

        $this->addExtensions($validator);

        $proxyValidator=new ValidatorProxy($validator);

        return $proxyValidator;
    }

}