<?php
namespace Plitz\Parser\Expressions;

use Plitz\Parser\Expression;

class GetAttribute extends Expression
{
    /**
     * @var string
     */
    private $attributeName;

    /**
     * @var Expression
     */
    private $expression;

    public function __construct(Expression $expression, $attributeName)
    {
        $this->attributeName = $attributeName;
        $this->expression = $expression;
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
    public function getAttributeName()
    {
        return $this->attributeName;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->expression->toString() . "." . $this->attributeName;
    }
}
