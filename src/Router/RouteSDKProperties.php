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
    protected $tableName;
    protected $responseKey;
    protected $singular;
    protected $plural;
    protected $propertyData;
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
            "class" => $this->getClass(),
            "function" => $this->getFunction(),
            "hydratable" => $this->isHydratable(),
            "tableName" => $this->getTableName(),
            "responseKey" => $this->getResponseKey(),
            "singular" => $this->getSingular(),
            "plural" => $this->getPlural(),
            "arguments" => $this->getArguments(),
            "propertyData" => $this->getPropertyData(),
        ];
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
     * @return RouteSdkProperties
     */
    public function setClass($class)
    {
        $this->class = $class;
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
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @param mixed $tableName
     * @return RouteSdkProperties
     */
    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
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
    public function getSingular()
    {
        return $this->singular;
    }

    /**
     * @param mixed $singular
     * @return RouteSdkProperties
     */
    public function setSingular($singular)
    {
        $this->singular = $singular;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPlural()
    {
        return $this->plural;
    }

    /**
     * @param mixed $plural
     * @return RouteSdkProperties
     */
    public function setPlural($plural)
    {
        $this->plural = $plural;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPropertyData()
    {
        return $this->propertyData;
    }

    /**
     * @param mixed $propertyData
     *
     * @return RouteSDKProperties
     */
    public function setPropertyData($propertyData)
    {
        $this->propertyData = $propertyData;
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
        $this->arguments = $arguments;
        return $this;
    }


}