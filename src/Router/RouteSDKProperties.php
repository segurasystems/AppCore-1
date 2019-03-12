<?php
/**
 * Created by PhpStorm.
 * User: wolfgang
 * Date: 10/03/2019
 * Time: 16:40
 */

namespace Gone\AppCore\Router;


class RouteSDKProperties implements \JsonSerializable
{
    protected $function;
    protected $hydratable = false;
    protected $responseKey;
    protected $classSource;
    protected $arguments;

    public static function Factory()
    {
        return new self();
    }

    public function jsonSerialize()
    {
        return $this->__toArray();
    }

    public function __toArray()
    {
        return [
            "function" => $this->getFunction(),
            "hydratable" => $this->isHydratable(),
            "responseKey" => $this->getResponseKey(),
            "arguments" => $this->getArguments(),
            "classSource" => $this->getClassSource(),
        ];
    }

    /**
     * @return mixed
     */
    public function getFunction()
    {
        return $this->function;
    }

    /**
     * @param mixed $function
     * @return RouteSdkProperties
     */
    public function setFunction($function)
    {
        $this->function = $function;
        return $this;
    }

    /**
     * @return bool
     */
    public function isHydratable(): bool
    {
        return $this->hydratable;
    }

    /**
     * @param bool $hydratable
     * @return RouteSdkProperties
     */
    public function setHydratable(bool $hydratable): RouteSdkProperties
    {
        $this->hydratable = $hydratable;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getResponseKey()
    {
        return $this->responseKey ?? $this->getSingular();
    }

    /**
     * @param mixed $responseKey
     * @return RouteSdkProperties
     */
    public function setResponseKey($responseKey)
    {
        $this->responseKey = $responseKey;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param mixed $arguments
     *
     * @return RouteSDKProperties
     */
    public function setArguments($arguments)
    {
        $this->arguments = [];
        $this->addArguments($arguments);
        return $this;
    }

    public function addArguments($arguments){
        foreach ($arguments as $name=>$argument){
            $this->addArgument($name,$argument);
        }
        return $this;
    }

    public function addArgument(string $name, array $argument)
    {
        $argument["name"] = $name;
        $this->arguments[$name] = [
            "in" => $argument["in"] ?? "path",
            "description" => $argument["description"] ?? null,
            "required" => $argument["required"] ?? false,
            "default" => $argument["default"] ?? null,
            "type" => $argument["default"] ?? null,
            "examples" => $argument["examples"] ?? [],
        ];
        return $this;
    }

    /**
     * @return mixed
     */
    public function getClassSource()
    {
        return $this->classSource;
    }

    /**
     * @param mixed $classSource
     * @return RouteSDKProperties
     */
    public function setClassSource($classSource)
    {
        $this->classSource = $classSource;
        return $this;
    }
}