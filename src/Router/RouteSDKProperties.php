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
    protected $class;
    protected $function;
    protected $hydratable = false;
    protected $responseKey;
    protected $returnsArray = false;
    protected $responseClass;
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
            "responseClass" => $this->getResponseClass(),
            "class" => $this->getClass(),
            "returnsArray" => $this->getReturnsArray(),
        ];
    }

    /**
     * @return bool
     */
    public function getReturnsArray(): bool
    {
        return $this->returnsArray;
    }

    /**
     * @param bool $returnsArray
     *
     * @return RouteSDKProperties
     */
    public function setReturnsArray(bool $returnsArray): RouteSDKProperties
    {
        $this->returnsArray = $returnsArray;
        return $this;
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
        return $this->responseKey;
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
        if($argument["required"] ?? false){
            $argument["cancelHydrate"] = false;
        }
        $this->arguments[$name] = [
            "in" => $argument["in"] ?? "path",
            "description" => $argument["description"] ?? null,
            "required" => $argument["required"] ?? false,
            "default" => $argument["default"] ?? null,
            "type" => $argument["type"] ?? null,
            "examples" => $argument["examples"] ?? [],
            "cancelHydrate" => $argument["cancelHydrate"] ?? false,
        ];
        return $this;
    }

    /**
     * @return mixed
     */
    public function getResponseClass()
    {
        return $this->responseClass;
    }

    /**
     * @param mixed $responseClass
     *
     * @return RouteSDKProperties
     */
    public function setResponseClass($responseClass)
    {
        $this->responseClass = $responseClass;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param mixed $class
     *
     * @return RouteSDKProperties
     */
    public function setClass($class)
    {
        $this->class = $class;
        return $this;
    }

}