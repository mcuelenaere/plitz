<?php
namespace Plitz\Parser\Expressions;

use Plitz\Parser\Expression;

class Variable extends Expression
{
    /**
     * @var string
     */
    private $variableName;

    public function __construct($variableName)
    {
        $this->variableName = $variableName;
    }

    /**
     * @return string
     */
    public function getVariableName()
    {
        return $this->variableName;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->variableName;
    }
}
