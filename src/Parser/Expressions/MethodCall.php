<?php
namespace Plitz\Parser\Expressions;

use Plitz\Parser\Expression;

class MethodCall extends Expression
{
    /**
     * @var string
     */
    private $methodName;

    /**
     * @var Expression[]
     */
    private $arguments;

    public function __construct($methodName, array $arguments)
    {
        $this->methodName = $methodName;
        $this->arguments = $arguments;
    }

    /**
     * @return string
     */
    public function getMethodName()
    {
        return $this->methodName;
    }

    /**
     * @return Expression[]
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->methodName . '(' . implode(', ', $this->arguments) . ')';
    }
}
