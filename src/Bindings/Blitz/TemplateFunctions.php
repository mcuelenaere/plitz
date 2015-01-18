<?php
namespace Plitz\Bindings\Blitz;

class TemplateFunctions
{
    /**
     * Helper method which reduces a variable to its requested properties.
     *
     * This function takes a value and a non-empty array of properties as varargs.
     * It then tries to resolve the properties of this value, one-by-one recursively.
     *
     * Eg:
     *   $foo = ['bar' => ['foo' => 'foobar']];
     *   var_dump(TemplateFunctions::fetchProperty($foo, 'bar', 'foo')); // gives 'foobar'
     *
     * @return mixed
     */
    public static function fetchProperty()
    {
        $args = func_get_args();
        if (count($args) < 2) {
            throw new \InvalidArgumentException('Expected at least 2 arguments');
        }
        $variable = array_shift($args);

        foreach ($args as $arg) {
            if (is_object($variable)) {
                $variable = &$variable->{$arg};
            } else if (is_array($variable)) {
                $variable = &$variable[$arg];
            } else {
                throw new \InvalidArgumentException('$variable is neither an object nor an array');
            }
        }

        return $variable;
    }
}
