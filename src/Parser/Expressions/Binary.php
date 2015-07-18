<?php
namespace Plitz\Parser\Expressions;

use Plitz\Parser\Expression;

class Binary extends Expression
{
    const OPERATOR_ADD      = '+';
    const OPERATOR_SUBTRACT = '-';
    const OPERATOR_MULTIPLY = '*';
    const OPERATOR_DIVIDE   = '/';
    const OPERATOR_MODULO   = '%';

    const OPERATOR_EQUALS                 = '==';
    const OPERATOR_NOT_EQUALS             = '!=';
    const OPERATOR_GREATER_THAN           = '>';
    const OPERATOR_GREATER_THAN_OR_EQUALS = '>=';
    const OPERATOR_LESS_THAN              = '<';
    const OPERATOR_LESS_THAN_OR_EQUALS    = '<=';

    const OPERATOR_OR = '||';
    const OPERATOR_AND = '&&';

    /**
     * @var Expression
     */
    private $left;

    /**
     * @var Expression
     */
    private $right;

    /**
     * @var string
     */
    private $operation;

    public function __construct(Expression $left, Expression $right, $operation)
    {
        $this->left = $left;
        $this->right = $right;
        $this->operation = $operation;
    }

    /**
     * @return Expression
     */
    public function getLeft()
    {
        return $this->left;
    }

    /**
     * @return Expression
     */
    public function getRight()
    {
        return $this->right;
    }

    /**
     * @return string
     */
    public function getOperation()
    {
        return $this->operation;
    }

    public function toString()
    {
        return $this->left->toString() . " " . $this->operation . " " . $this->right->toString();
    }
}
