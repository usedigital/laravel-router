<?php


namespace UseDigital\LaravelRouter\Models;


use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\Types\Self_;
use UseDigital\LaravelRouter\Utils\RouterHelpers;

class RouteGroupModel
{
    public $title;

    private $properties = ["prefix", "as", "middleware", "namespace", "domain"];

    private $attributes = [];

    /**
     * @var Collection|RouteModel[]|RouteGroupModel[]
     */
    public $itens;

    public function __construct()
    {
        $this->itens = collect();
    }

    public function getProps(){

        //Limpar attributes
        $attributes = collect($this->attributes)->toArray();

        return RouterHelpers::shortArrayStructureString($attributes);
    }

    public function __set($key, $value){

        $method_name = "set".ucwords($key)."Attribute";

        if(method_exists($this, $method_name)){
            $this->$method_name($value);
        }
        else if(collect($this->properties)->contains($key)){
            $this->attributes[$key] = $value;
        }else if(property_exists(self::class, $key)){
            $this->$key = $value;
        }

    }

    public function __get($key){
        return $this->$key ?? $this->attributes[$key] ?? null;
    }

    private function setDomainAttribute($value){
        $this->attributes["domain"] = $value; //.(substr($value, -1) == "." ? "" : ".").config("app.domain");
    }

    private function setNamespaceAttribute($value){
        $this->attributes["namespace"] = ($value == '""') ? "" : $value;
    }

    public function addRoute($match, $url, $as, $uses, $middleware){
        $this->itens->add(new RouteModel($match, $url, $as, $uses, $middleware));
    }

    public function addGroup($prefix, $as, $middleware){
        $this->itens->add(new RouteGroupModel($prefix, $as, $middleware));
    }
}
