<?php

namespace Proengsoft\JsValidation;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Proengsoft\JsValidation;

class JsValidationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        // First we bootstrap configurations
        $this->bootstrapConfigs();

        $this->bootstrapViews();
        $this->publishAssets();
        $this->bootstrapValidator();
    }

    /**
     * Register the application services.
     */
    public function register()
    {

        //$this->app->bind('Illuminate\Validation\Validator','Proengsoft\JsValidation\Validator');
        //class_alias('Proengsoft\JsValidation\Validator','Illuminate\Validation\Validator');
        //class_alias('\Intervention\Validation\ValidatorExtension','\Proengsoft\JsValidation\Prova');


        //$this->app->bind('Illuminate\Contracts\Validation\Factory','Proengsoft\JsValidation\ValidatorFactory');
        $this->registerValidationFactory();

        $this->app->bind('jsvalidator', function (Application $app) {

            $selector = Config::get('jsvalidation.form_selector');
            $view = Config::get('jsvalidation.view');

            $validator = new Manager($selector, $view);
            //$validatorFactory = $app->make('Illuminate\Contracts\Validation\Factory');
           // dd($validatorFactory);
            return new Factory($validator, $app);
        });
    }

    /**
     * Register Validator resolver.
     */
    protected function bootstrapValidator()
    {
        //$v=$this->app->make('Illuminate\Contracts\Validation\Factory');
        //return $v;

        /*
        $this->app['validator']->resolver(function ($translator, $data, $rules, $messages = array(), $customAttributes = array()) {
            return new Validator($translator, $data, $rules, $messages, $customAttributes);
        });
        */





    }

    /**
     * Register the validation factory.
     *
     * @return void
     */
    protected function registerValidationFactory()
    {
        $this->app->bind('Illuminate\Validation\Factory', 'Proengsoft\JsValidation\ValidatorFactory');

        $this->app->singleton('validator', function ($app) {
            $validator = new ValidatorFactory($app['translator'], $app);

            // The validation presence verifier is responsible for determining the existence
            // of values in a given data collection, typically a relational database or
            // other persistent data stores. And it is used to check for uniqueness.
            if (isset($app['validation.presence'])) {
                $validator->setPresenceVerifier($app['validation.presence']);
            }

            return $validator;
        });
    }

    /**
     * Configure and publish views.
     */
    protected function bootstrapViews()
    {
        $viewPath = realpath(__DIR__.'/../resources/views');

        $this->loadViewsFrom($viewPath, 'jsvalidation');
        $this->publishes([
            $viewPath => $this->app['path.base'].'/resources/views/vendor/jsvalidation',
        ], 'views');
    }

    /**
     * Load and publishes configs.
     */
    protected function bootstrapConfigs()
    {
        $configFile = realpath(__DIR__.'/../config/jsvalidation.php');

        $this->mergeConfigFrom($configFile, 'jsvalidation');
        $this->publishes([$configFile => $this->app['path.config'].'/jsvalidation.php'], 'config');
    }

    /**
     * Publish public assets.
     */
    protected function publishAssets()
    {
        $this->publishes([
            realpath(__DIR__.'/../public') => $this->app['path.public'].'/vendor/jsvalidation',
        ], 'public');
    }
}
