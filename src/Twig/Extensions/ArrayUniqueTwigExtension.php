<?php
namespace Gone\AppCore\Twig\Extensions;

class ArrayUniqueTwigExtension extends \Twig_Extension
{
    public function getName()
    {
        return 'ArrayUnique Twig Extension';
    }
    
    public function getFilters()
    {
        $filters = [];
        $methods = ['unique'];
        foreach ($methods as $method) {
            $filters[$method] = new \Twig_Filter($method, [$this, $method]);
        }
        return $filters;
    }
    
    public function unique($array)
    {
        if (is_array($array)) {
            return array_unique($array, SORT_REGULAR);
        }
        return $array;
    }
}
