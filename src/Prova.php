<?php
/**
 * Created by PhpStorm.
 * User: Albert
 * Date: 10/08/2015
 * Time: 20:50
 */

namespace Proengsoft\JsValidation;
use Illuminate\Validation\Validator as BaseValidator;

class Prova extends BaseValidator
{

    protected function isImplicit($rule) {
        dd("sssss");
        return parent::isImplicit($rule);
    }
}