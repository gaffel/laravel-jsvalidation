<?php

namespace Proengsoft\JsValidation\Traits;

use Closure;

trait ValidatorMethods
{
    /**
     * Validation rules
     *
     * @var array
     */
    protected $rules = array();

    /**
     * Get the validation rules.
     *
     * @return array
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Determine if the given attribute has a rule in the given set.
     *
     * @param  string  $attribute
     * @param  string|array  $rules
     * @return bool
     */
    protected function hasRule($attribute, $rules)
    {
        return ! is_null($this->getRule($attribute, $rules));
    }

    /**
     * Get a rule and its parameters for a given attribute.
     *
     * @param  string  $attribute
     * @param  string|array  $rules
     * @return array|null
     */
    protected function getRule($attribute, $rules)
    {
        if ( ! array_key_exists($attribute, $this->rules))
        {
            return null;
        }

        $rules = (array) $rules;

        foreach ($this->rules[$attribute] as $rule)
        {
            list($rule, $parameters) = $this->parseRule($rule);

            if (in_array($rule, $rules)) return [$rule, $parameters];
        }
    }

    /**
     * Extract the rule name and parameters from a rule.
     *
     * @param  array|string  $rules
     * @return array
     */
    protected function parseRule($rules)
    {
        if (is_array($rules))
        {
            return $this->parseArrayRule($rules);
        }

        return $this->parseStringRule($rules);
    }

    /**
     * Parse an array based rule.
     *
     * @param  array  $rules
     * @return array
     */
    protected function parseArrayRule(array $rules)
    {
        return array(studly_case(trim(array_get($rules, 0))), array_slice($rules, 1));
    }

    /**
     * Parse a string based rule.
     *
     * @param  string  $rules
     * @return array
     */
    protected function parseStringRule($rules)
    {
        $parameters = [];

        // The format for specifying validation rules and parameters follows an
        // easy {rule}:{parameters} formatting convention. For instance the
        // rule "Max:3" states that the value may only be three letters.
        if (strpos($rules, ':') !== false)
        {
            list($rules, $parameter) = explode(':', $rules, 2);

            $parameters = $this->parseParameters($rules, $parameter);
        }

        return array(studly_case(trim($rules)), $parameters);
    }

    /**
     * Parse a parameter list.
     *
     * @param  string  $rule
     * @param  string  $parameter
     * @return array
     */
    protected function parseParameters($rule, $parameter)
    {
        if (strtolower($rule) == 'regex') return array($parameter);

        return str_getcsv($parameter);
    }

    /**
     * Add an error message to the validator's collection of messages.
     *
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return void
     */
    protected function addError($attribute, $rule, $parameters)
    {
        $message = $this->getMessage($attribute, $rule);

        $message = $this->doReplacements($message, $attribute, $rule, $parameters);

        $this->messages->add($attribute, $message);
    }

    /**
     * Get the validation message for an attribute and rule.
     *
     * @param  string  $attribute
     * @param  string  $rule
     * @return string
     */
    protected function getMessage($attribute, $rule)
    {
        $lowerRule = snake_case($rule);

        $inlineMessage = $this->getInlineMessage($attribute, $lowerRule);

        // First we will retrieve the custom message for the validation rule if one
        // exists. If a custom validation message is being used we'll return the
        // custom message, otherwise we'll keep searching for a valid message.
        if ( ! is_null($inlineMessage))
        {
            return $inlineMessage;
        }

        $customKey = "validation.custom.{$attribute}.{$lowerRule}";

        $customMessage = $this->translator->trans($customKey);

        // First we check for a custom defined validation message for the attribute
        // and rule. This allows the developer to specify specific messages for
        // only some attributes and rules that need to get specially formed.
        if ($customMessage !== $customKey)
        {
            return $customMessage;
        }

        // If the rule being validated is a "size" rule, we will need to gather the
        // specific error message for the type of attribute being validated such
        // as a number, file or string which all have different message types.
        elseif (in_array($rule, $this->sizeRules))
        {
            return $this->getSizeMessage($attribute, $rule);
        }

        // Finally, if no developer specified messages have been set, and no other
        // special messages apply for this rule, we will just pull the default
        // messages out of the translator service for this validation rule.
        $key = "validation.{$lowerRule}";

        if ($key != ($value = $this->translator->trans($key)))
        {
            return $value;
        }

        return $this->getInlineMessage(
            $attribute, $lowerRule, $this->fallbackMessages
        ) ?: $key;
    }

    /**
     * Get the inline message for a rule if it exists.
     *
     * @param  string  $attribute
     * @param  string  $lowerRule
     * @param  array   $source
     * @return string
     */
    protected function getInlineMessage($attribute, $lowerRule, $source = null)
    {
        $source = $source ?: $this->customMessages;

        $keys = array("{$attribute}.{$lowerRule}", $lowerRule);

        // First we will check for a custom message for an attribute specific rule
        // message for the fields, then we will check for a general custom line
        // that is not attribute specific. If we find either we'll return it.
        foreach ($keys as $key)
        {
            if (isset($source[$key])) return $source[$key];
        }
    }

    /**
     * Get the proper error message for an attribute and size rule.
     *
     * @param  string  $attribute
     * @param  string  $rule
     * @return string
     */
    protected function getSizeMessage($attribute, $rule)
    {
        $lowerRule = snake_case($rule);

        // There are three different types of size validations. The attribute may be
        // either a number, file, or string so we will check a few things to know
        // which type of value it is and return the correct line for that type.
        $type = $this->getAttributeType($attribute);

        $key = "validation.{$lowerRule}.{$type}";

        return $this->translator->trans($key);
    }

    /**
     * Get the data type of the given attribute.
     *
     * @param  string  $attribute
     * @return string
     */
    protected function getAttributeType($attribute)
    {
        // We assume that the attributes present in the file array are files so that
        // means that if the attribute does not have a numeric rule and the files
        // list doesn't have it we'll just consider it a string by elimination.
        if ($this->hasRule($attribute, $this->numericRules))
        {
            return 'numeric';
        }
        elseif ($this->hasRule($attribute, array('Array')))
        {
            return 'array';
        }
        elseif (array_key_exists($attribute, $this->files))
        {
            return 'file';
        }

        return 'string';
    }

    /**
     * Replace all error message place-holders with actual values.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function doReplacements($message, $attribute, $rule, $parameters)
    {
        $message = str_replace(':attribute', $this->getAttribute($attribute), $message);

        if (isset($this->replacers[snake_case($rule)]))
        {
            $message = $this->callReplacer($message, $attribute, snake_case($rule), $parameters);
        }
        elseif (method_exists($this, $replacer = "replace{$rule}"))
        {
            $message = $this->$replacer($message, $attribute, $rule, $parameters);
        }

        return $message;
    }

    /**
     * Get the displayable name of the attribute.
     *
     * @param  string  $attribute
     * @return string
     */
    protected function getAttribute($attribute)
    {
        // The developer may dynamically specify the array of custom attributes
        // on this Validator instance. If the attribute exists in this array
        // it takes precedence over all other ways we can pull attributes.
        if (isset($this->customAttributes[$attribute]))
        {
            return $this->customAttributes[$attribute];
        }

        $key = "validation.attributes.{$attribute}";

        // We allow for the developer to specify language lines for each of the
        // attributes allowing for more displayable counterparts of each of
        // the attributes. This provides the ability for simple formats.
        if (($line = $this->translator->trans($key)) !== $key)
        {
            return $line;
        }

        // If no language line has been specified for the attribute all of the
        // underscores are removed from the attribute name and that will be
        // used as default versions of the attribute's displayable names.
        return str_replace('_', ' ', snake_case($attribute));
    }

    /**
     * Call a custom validator message replacer.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function callReplacer($message, $attribute, $rule, $parameters)
    {
        $callback = $this->replacers[$rule];

        if ($callback instanceof Closure)
        {
            return call_user_func_array($callback, func_get_args());
        }
        elseif (is_string($callback))
        {
            return $this->callClassBasedReplacer($callback, $message, $attribute, $rule, $parameters);
        }
    }

    /**
     * Call a class based validator message replacer.
     *
     * @param  string  $callback
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function callClassBasedReplacer($callback, $message, $attribute, $rule, $parameters)
    {
        list($class, $method) = explode('@', $callback);

        return call_user_func_array(array($this->container->make($class), $method), array_slice(func_get_args(), 1));
    }

}