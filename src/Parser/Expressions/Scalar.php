<?php
namespace Plitz\Parser\Expressions;

use Plitz\Parser\Expression;

class Scalar extends Expression
{
    /**
     * @var int|double|string|bool
     */
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return bool|float|int|string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return json_encode($this->value);
    }
}
