<?php
namespace Plitz\Parser\Expressions;

use Plitz\Parser\Expression;

class Unary extends Expression
{
    const OPERATION_NOT = '!';
    const OPERATION_NEGATE = '-';

    /**
     * @var Expression
     */
    private $expression;

    /**
     * @var string
     */
    private $operation;

    public function __construct(Expression $expression, $operation)
    {
        $this->expression = $expression;
        $this->operation = $operation;
    }

    /**
     * @return Expression
     */
    public function getExpression()
    {
        return $this->expression;
    }

    /**
     * @return string
     */
    public function getOperation()
    {
        return $this->operation;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->operation . " " . $this->expression->toString();
    }
}
