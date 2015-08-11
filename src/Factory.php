<?php

namespace Proengsoft\JsValidation;

use Proengsoft\JsValidation\Exceptions\FormRequestArgumentException;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Contracts\Validation\Factory as FactoryContract;
use Illuminate\Contracts\Foundation\Application;

class Factory
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Validation\Factory
     */
    protected $validator;

    /**
     * Javascript validator instance.
     *
     * @var Manager
     */
    protected $manager;

    /**
     * Illuminate application instance
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * Create a new Validator factory instance.
     *
     * @param \Proengsoft\JsValidation\Manager $manager
     * @param Application $app
     * @internal param FactoryContract $validator
     */
    public function __construct(Manager $manager, Application $app)
    {
        $this->manager = $manager;
        $this->app=$app;
    }

    /**
     * Creates JsValidator instance based on rules and message arrays.
     *
     * @param array       $rules
     * @param array       $messages
     * @param array       $customAttributes
     * @param null|string $selector
     *
     * @return \Proengsoft\JsValidation\Manager
     */
    public function make(array $rules, array $messages = array(), array $customAttributes = array(), $selector = null)
    {
        $validator = $this->getValidatorInstance($rules, $messages, $customAttributes);

        return $this->createValidator($validator, $selector);
    }

    /**
     * Creates JsValidator instance based on FormRequest.
     *
     * @param $formRequest
     * @param null $selector
     *
     * @return Manager
     *
     * @throws FormRequestArgumentException
     */
    public function formRequest($formRequest, $selector = null)
    {
        if (!is_subclass_of($formRequest,  'Illuminate\\Foundation\\Http\\FormRequest')) {
            $className = is_object($formRequest) ? get_class($formRequest) : (string) $formRequest;
            throw new FormRequestArgumentException($className);
        }

        $formRequest = is_string($formRequest) ? new $formRequest() : $formRequest;
        $validator = $this->getValidatorInstance($formRequest->rules(), $formRequest->messages(), $formRequest->attributes());

        return $this->createValidator($validator, $selector);
    }

    /**
     * Creates JsValidator instance based on Validator.
     *
     * @param ValidatorContract $validator
     * @param string|null       $selector
     *
     * @return Manager
     */
    public function validator(ValidatorContract $validator, $selector = null)
    {
        return $this->createValidator($validator, $selector);
    }

    /**
     * Creates JsValidator instance based on Validator.
     *
     * @param ValidatorContract $validator
     * @param string|null       $selector
     *
     * @return Manager
     */
    protected function createValidator(ValidatorContract $validator, $selector = null)
    {

        $this->manager->selector($selector);
        $this->manager->setValidator($validator);

        return $this->manager;
    }

    /**
     * Get the validator instance for the request.
     *
     * @param array       $rules
     * @param array       $messages
     * @param array       $customAttributes
     *
     * @return \Illuminate\Validation\Validator
     */
    protected function getValidatorInstance(array $rules, array $messages = array(), array $customAttributes = array())
    {
        return  $this->app['validator']->make([], $rules, $messages, $customAttributes);
    }
}
