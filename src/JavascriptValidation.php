<?php

namespace Proengsoft\JsValidation;


use Illuminate\Validation\Validator;
use Proengsoft\JsValidation\Traits\ValidatorMethods;
use Symfony\Component\HttpFoundation\File\File;
use ReflectionMethod;
use ReflectionProperty;

class JavascriptValidation
{
    use ValidatorMethods;

    const JSVALIDATION_DISABLE = 'NoJsValidation';

    /**
     * @var Validator
     */
    protected $validator;

    /**
     * @param Validator $validator
     */
    public function __construct( $validator) {

        $this->rules = $validator->getRules();
        $this->translator = $validator->getTranslator();
        $this->customMessages = $validator->getCustomMessages();
        $this->fallbackMessages = $validator->getFallbackMessages();

        $this->validator = $validator;
    }

    /**
     * Returns view data to render javascript.
     *
     * @return array
     */
    public function validations()
    {
        $jsValidations = $this->generateValidations();

        return [
            'rules' => $jsValidations,
            'messages' => array(),
        ];
    }

    /**
     * Generate Javascript validations.
     *
     * @return array
     */
    protected function generateValidations()
    {
        // Check if JS Validation is disabled for this attribute

        $vAttributes = array_filter(array_keys($this->rules), [$this, 'jsValidationEnabled']);
        $vRules = array_intersect_key($this->rules, array_flip($vAttributes));

        $messages=$this->getJsMessages($vRules);
        dd($messages);

        // Convert each rules and messages
        $convertedRules = array_map([$this, 'jsConvertRules'], array_keys($vRules), $vRules);

        $convertedRules = array_filter($convertedRules, function ($value) {
            return !empty($value['rules']);
        });

        // Format results
        return array_reduce($convertedRules, function ($result, $item) {
            $attribute = $item['attribute'];
            $rule = $item['rules'];
            $result[$attribute] = (empty($result[$attribute])) ? array() : $result[$attribute];
            $result[$attribute] = array_merge($result[$attribute], $rule);
            return $result;
        }, array());
    }

    /**
     * Check if JS Validation is disabled for attribute.
     *
     * @param $attribute
     *
     * @return bool
     */
    public function jsValidationEnabled($attribute)
    {
        return !$this->hasRule($attribute, self::JSVALIDATION_DISABLE);
    }

    /**
     * Make Laravel Validations compatible with JQuery Validation Plugin.
     *
     * @param $attribute
     * @param $rules
     *
     * @return array
     */
    protected function jsConvertRules($attribute, $rules)
    {
        $jsRules = [];
        $jsAttribute = $attribute;


        foreach ($rules as $rawRule) {
            list($rule, $parameters) = $this->parseRule($rawRule);
            list($jsAttribute, $jsRule, $jsParams) = $this->getJsRule($attribute, $rule, $parameters);
            if ($jsRule) {
                $jsRules[$jsRule][] = array(
                    $rule, $jsParams,
                    $this->getMessage($attribute, $rule, $parameters),
                    false, //$this->isImplicit($rule),
                );
            }
        }

        return array(
            'attribute' => $jsAttribute,
            'rules' => $jsRules,
        );
    }

    /**
     * Return parsed Javascript Rule.
     *
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return array
     */
    protected function getJsRule($attribute, $rule, $parameters)
    {
        $method = "jsRule{$rule}";
        $jsRule = false;

        /*
        if ($this->isRemoteRule($rule)) {
            list($attribute, $parameters) = $this->jsRemoteRule($attribute);
            $jsRule = 'laravelValidationRemote';
        } else
        */
        if (method_exists($this, $method)) {
            list($attribute, $parameters) = $this->$method($attribute, $parameters);
            $jsRule = 'laravelValidation';
        } elseif (method_exists($this->validator, "validate{$rule}")) {
            $jsRule = 'laravelValidation';
        }

        return [$attribute, $jsRule, $parameters];
    }

    function getJsMessages($rules) {

        $newRules=[];
        $newData=[];
        $customMessages=[];
        $messages=[];
        foreach ($rules as $attribute=>$rule) {
            $newData[$attribute]="";
            $rule=(array) $rule;
            foreach ($rule as $value) {
                $newRules[$attribute][]='jsvalidation_'.$value;
                list($ruleName, $parameters) = $this->parseRule($value);
                $messages[$attribute][$ruleName]=$this->getMessage($attribute,$ruleName, $parameters);

                /*
                $newRule=snake_case($ruleName);
                $this->validator->addImplicitExtension('jsvalidation_'.$newRule,function(){return false;});
                $validator=$this->validator;
                $method = new ReflectionMethod($validator, "doReplacements");
                $method->setAccessible(true);

                $this->validator->addReplacer('jsvalidation_'.$newRule,function($message, $attribute, $rule, $parameters) use ($ruleName, $validator, $method)  {
                        $rule=studly_case(preg_replace('/^jsvalidation_/','',$rule));
                        return $method->invokeArgs($validator, [$message, $attribute, $rule, $parameters]);

                });

                $customMessages["{$attribute}.jsvalidation_{$newRule}"]=$this->getMessage($attribute,$ruleName);
                */
            }
            //$rules[$i]='jsvalidation_'.snake_case($rule);

        }
        dd($messages);
        //dd($customMessages);
        $this->validator->setRules($newRules);
        $this->validator->setData($newData);
        $this->validator->setCustomMessages($customMessages);
        dd($this->validator->messages());

        return [];
    }

    /**
     *  Replace javascript error message place-holders with actual values.
     *
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     *
     * @return mixed
     */
    protected function __getJsMessage($attribute, $rule, $parameters)
    {


        $message = $this->getMessage($attribute, $rule);


        dd($rule);

        $message = $this->getTypeMessage($attribute, $rule);

        if (isset($this->replacers[snake_case($rule)])) {
            $message = $this->doReplacements($message, $attribute, $rule, $parameters);
        } elseif (method_exists($this, $replacer = "jsReplace{$rule}")) {
            $message = str_replace(':attribute', $this->getAttribute($attribute), $message);
            $message = $this->$replacer($message, $attribute, $rule, $parameters);
        } else {
            $message = $this->doReplacements($message, $attribute, $rule, $parameters);
        }

        return $message;
    }

    protected function getJsMessage($attribute, $rule, $parameters)
    {
        $fakeData = array();
        $validator=$this->getValidatorInstance();
        $previousData=$validator->getData();


        if ($rule == 'RequiredIf') {
            $fakeData[$parameters[0]]=$parameters[1];
        }




        $validator->setData($fakeData);

        $message = $this->getMessage($attribute, $rule, $parameters);
        $message = $this->doReplacements($message, $attribute, $rule, $parameters);

            //$message = $this->callProtectedMethod($validator,'getMessage',[$attribute, $rule]);
        //$message = $this->callProtectedMethod($validator,'doReplacements',[$message, $attribute, $rule,$parameters]);

        $validator->setData($previousData);

        return $message;

    }

    /**
     * Get the message considering the data type.
     *
     * @param string $attribute
     * @param string $rule
     *
     * @return string
     */
    private function getTypeMessage($attribute, $rule)
    {
        // find more elegant solution to set the attribute file type
        $prevFiles = $this->files;
        if ($this->hasRule($attribute, array('Mimes', 'Image'))) {
            if (!array_key_exists($attribute, $this->files)) {
                $this->files[$attribute] = false;
            }
        }



        $message = $this->getMessage($attribute, $rule);
        $this->files = $prevFiles;

        return $message;
    }




    public function getValidatorInstance()
    {
        return $this->validator;
    }


    public function getSizeRules()
    {
        return $this->getProtectedProperty($this->validator,'sizeRules');
    }


    /**
     * Get the validation message for an attribute and rule.
     *
     * @param  string  $attribute
     * @param  string  $rule
     * @return string
     */
    protected function getMessage($attribute, $rule, $parameters, $type = null)
    {
        $message = null;
        if (in_array($rule, $this->getSizeRules()) && is_null($type)) {
            $message=$this->getMessage($attribute, $rule, $parameters, 'file');
        }

        $validator=$this->getValidatorInstance();

        $previousData=$validator->getData();
        $previousFiles=$validator->getFiles();

        $validator->setData(
            $this->getFakeData($attribute, $rule, $parameters, $type)
        );

        $result = $this->callProtectedMethod($this->validator,'getMessage',[$attribute, $rule]);
        $result = $this->callProtectedMethod($validator,'doReplacements',[$result, $attribute, $rule,$parameters]);

        $validator->setData($previousData);
        $validator->setFiles($previousFiles);

        return $message? [$result, $message]:$result;

    }

    protected function getFakeData($attribute, $rule, $parameters, $type=null)
    {
        if ($rule == 'RequiredIf') {
            return array($parameters[0]=>$parameters[1]);
        }

        if ($type === 'file') {
            return array(
                $attribute => new File('fake_path',false)
            );
        }

        return array();
    }


    /**
     * Determine if a given rule implies the attribute is required.
     *
     * @param  string  $rule
     * @return bool
     */

    protected function isImplicit($rule)
    {
        return $this->callProtectedMethod($this->validator,'isImplicit',[$rule]);
    }


    private function callProtectedMethod($instance, $methodName, $parameters = null)
    {
        $method = new ReflectionMethod($instance, $methodName);
        $method->setAccessible(true);

        if (is_array($parameters))
        {
            return $method->invokeArgs($instance, $parameters);
        }
        return $method->invoke($instance);

    }


    private function getProtectedProperty($instance, $name)
    {
        $property = new ReflectionProperty($instance, $name);
        $property->setAccessible(true);

        return $property->getValue($instance);

    }





}