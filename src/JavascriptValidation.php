<?php

namespace Proengsoft\JsValidation;


use Illuminate\Validation\Validator;
use Proengsoft\JsValidation\Traits\ValidatorMethods;

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
        $this->validator = $validator;
    }

    /**
     * Returns view data to render javascript.
     *
     * @return array
     */
    public function validationData()
    {
        $jsValidations = $this->generateJavascriptValidations();

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
    public function generateJavascriptValidations()
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
                    $this->getJsMessage($attribute, $rule, $parameters),
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
        foreach ($rules as $i=>$rule) {
            $newData[$i]="fake";
            $rule=(array) $rule;
            foreach ($rule as $value) {
                $newRules[$i][]='jsvalidation_'.$value;
                list($ruleName) = $this->parseRule($value);
                $ruleName=snake_case($ruleName);
                $this->validator->addExtension('jsvalidation_'.$ruleName,function(){return false;});
            }
            //$rules[$i]='jsvalidation_'.snake_case($rule);

        }
        //dd($newRules);
        $this->validator->setRules($newRules);
        $this->validator->setData($newData);
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
    protected function getJsMessage($attribute, $rule, $parameters)
    {

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





}