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
    protected $modelData;
    protected $properties;

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
            "modelData" => $this->getModelData(),
            "properties" => $this->getProperties(),
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
    public function getModelData()
    {
        return $this->modelData;
    }

    /**
     * @param mixed $modelData
     * @return RouteSdkProperties
     */
    public function setModelData($modelData)
    {
        $this->modelData = $modelData;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param mixed $properties
     * @return RouteSdkProperties
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;
        return $this;
    }

    public function addCallbackProperty(string $name, array $property)
    {
        $property["name"] = $name;
        $this->properties[$name] = [
            "in" => $property["in"] ?? "path",
            "description" => $property["description"] ?? null,
            "required" => $property["required"] ?? false,
            "default" => $property["default"] ?? null,
            "type" => $property["default"] ?? null,
            "examples" => [],
        ];
        return $this;
    }

    public function addCallbackProperties($properties)
    {
        foreach ($properties as $name => $property) {
            $this->addCallbackProperty($name, $property);
        }
        return $this;
    }
}