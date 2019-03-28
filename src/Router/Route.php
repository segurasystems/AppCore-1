<?php

namespace Gone\AppCore\Router;

use Slim\App;

class Route
{
    const ACCESS_PUBLIC = 'public';
    const ACCESS_PRIVATE = 'private';

    protected $name;
    protected $callback;
//    protected $SDKClass;
//    protected $SDKFunction;
//    protected $SDKTemplate = "callback";
//    protected $SDKModelSafe = false;
//    protected $SDKTableName;
//    protected $SDKHydrate = false;
//    protected $SDKResponseKey;
    protected $routerPattern;
    protected $httpEndpoint;
    protected $httpMethod = "GET";
    protected $weight = 0;
//    protected $singular;
//    protected $plural;
//    protected $properties;
//    protected $propertyData = [];
//    protected $propertyOptions;
    protected $exampleEntity;
    protected $exampleEntityFinderFunction;
//    protected $callbackProperties = [];
    protected $access = self::ACCESS_PUBLIC;
    protected $arguments = [];

    protected $sdkClass;
    protected $sdkFunction;

    protected $sdkProperties = null;

    public static function Factory(): Route
    {
        return new Route();
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param array $arguments
     *
     * @return Route
     */
    public function setArguments(array $arguments): Route
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function getUniqueIdentifier()
    {
        return implode(
            "::",
            [
                $this->getRouterPattern(),
                $this->getHttpMethod(),
                "Weight={$this->getWeight()}",
            ]
        );
    }

    /**
     * @return mixed
     */
    public function getHttpMethod()
    {
        return $this->httpMethod;
    }

    /**
     * @param mixed $httpMethod
     *
     * @return Route
     */
    public function setHttpMethod($httpMethod): Route
    {
        $this->httpMethod = $httpMethod;
        return $this;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): Route
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRouterPattern()
    {
        return $this->routerPattern;
    }

    /**
     * @param mixed $routerPattern
     *
     * @return Route
     */
    public function setRouterPattern($routerPattern): Route
    {
        $this->routerPattern = $routerPattern;
        return $this;
    }

    /**
     * @param callable $finderFunction
     *
     * @return Route
     */
    public function setExampleEntityFindFunction(callable $finderFunction): Route
    {
        $this->exampleEntityFinderFunction = $finderFunction;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExampleEntity()
    {
        if (!$this->exampleEntity && $this->exampleEntityFinderFunction) {
            $function = $this->exampleEntityFinderFunction;
            $this->exampleEntity = $function();
        }
        return $this->exampleEntity;
    }

    /**
     * @param mixed $exampleEntity
     *
     * @return Route
     */
    public function setExampleEntity($exampleEntity): Route
    {
        $this->exampleEntity = $exampleEntity;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return Route
     */
    public function setName($name): Route
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param App $app
     *
     * @return \Slim\App
     */
    public function populateRoute(App $app): App
    {
        #echo "Populating: {$this->getHttpMethod()} {$this->getRouterPattern()}\n";
        $mapping = $app->map(
            [$this->getHttpMethod()],
            $this->getRouterPattern(),
            $this->getCallback()
        );

        $mapping->setName($this->getName() ? $this->getName() : "Unnamed Route");
        $mapping->setArgument('access', $this->getAccess());
        foreach ($this->getArguments() as $key => $value) {
            $mapping->setArgument(trim(strtolower($key)), $value);
        }
        return $app;
    }

    /**
     * @return mixed
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * @param mixed $callback
     *
     * @return Route
     */
    public function setCallback($callback): Route
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHttpEndpoint()
    {
        return $this->httpEndpoint;
    }

    /**
     * @param mixed $httpEndpoint
     *
     * @return Route
     */
    public function setHttpEndpoint($httpEndpoint): Route
    {
        $this->httpEndpoint = $httpEndpoint;
        return $this;
    }

    /**
     * @return string
     */
    public function getAccess()
    {
        return $this->access;
    }

    /**
     * @param string $access
     *
     * @return Route
     */
    public function setAccess($access = self::ACCESS_PUBLIC): Route
    {
        $this->access = $access;
        return $this;
    }

    public function setSDKRef(string $class, string $function){
        $this->sdkClass = $class;
        $this->sdkFunction = $function;
        return $this;
    }

    public function getSDKClass(){
        return $this->sdkClass;
    }

    public function getSDKFunction(){
        return $this->sdkFunction;
    }

    public function setSdkProperties (RouteSDKProperties $properties){
        $this->sdkProperties = $properties->__toArray();
        return $this;
    }

    public function getSdkProperties(){
        return $this->sdkProperties;
    }

}
