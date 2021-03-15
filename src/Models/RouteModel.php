<?php


namespace UseDigital\LaravelRouter\Models;


use Illuminate\Support\Str;
use UseDigital\LaravelRouter\Utils\RouterHelpers;

/**
 * Class RouteModel
 *
 * @property string $name
 * @property string $url
 * @property string $method
 * @property string $uses
 * @property string $as
 * @property string $middleware
 */
class RouteModel
{
    private $method;
    private $url;
    private $name;

    private $properties = ["uses", "as", "middleware"];

    private $attributes = [];

    public function __construct()
    {
        $this->setMethod();
    }

    public function getProps(){

        //Limpar attributes
        $attributes = collect($this->attributes)->filter()->toArray();

        return RouterHelpers::shortArrayStructureString($attributes);
    }

    public function __set($key, $value)
    {
        $method_name = "set{$key}";

        if(method_exists(self::class, $method_name)){

            $this->$method_name($value);

        }else if(collect($this->properties)->contains($key)){

            $this->attributes[$key] = $value;

        }else if(property_exists(self::class, $key)){

            $this->$key = $value;

        }
    }

    public function __get($key){
        return $this->$key ?? $this->attributes[$key] ?? null;
    }

    public function setMethod($value = null){

        $methods = null;

        if(empty($value)) $value = config("router.defaults.method");

        preg_match_all('/([a-zA-Z0-9]*)/mi', $value, $methods);

        if($methods && count($methods))
            $methods = collect($methods[0])->filter()->values()->toJson();
        else
            $methods = collect(config("router.defaults.method"))->filter()->values()->toJson();


        $this->method = $methods;

        return $this;
    }
}
