<?php
namespace Plitz\Expressions;

use Plitz\Parser\Expression;
use Plitz\Parser\Expressions\Binary;
use Plitz\Parser\Expressions\GetAttribute;
use Plitz\Parser\Expressions\MethodCall;
use Plitz\Parser\Expressions\Unary;

/**
 * Invokes the given callback for each expression and its sub-expressions.
 *
 * Expects the callback to return either the same expression or a new one. If
 * a new one is returned, the parent expression will be modified to contain the
 * new.
 */
class ExpressionProcessor
{
    /**
     * @var callable
     */
    private $callback;

    /**
     * @param callable $callback
     */
    public function __construct($callback)
    {
        $this->callback = $callback;
    }

    protected function visitBinary(Binary $binary)
    {
        $newLeft = $this->__invoke($binary->getLeft());
        $newRight = $this->__invoke($binary->getRight());

        if ($newLeft !== $binary->getLeft() || $newRight !== $binary->getRight()) {
            return new Binary($newLeft, $newRight, $binary->getOperation());
        } else {
            return $binary;
        }
    }

    protected function visitGetAttribute(GetAttribute $getAttribute)
    {
        $newExpression = $this->__invoke($getAttribute->getExpression());

        if ($newExpression !== $getAttribute->getExpression()) {
            return new GetAttribute($newExpression, $getAttribute->getAttributeName());
        } else {
            return $getAttribute;
        }
    }

    protected function visitMethodCall(MethodCall $methodCall)
    {
        $newArguments = [];
        $argumentsHaveChanged = false;
        foreach ($methodCall->getArguments() as $argument) {
            $newArgument = $this->__invoke($argument);
            $argumentsHaveChanged |= ($newArgument !== $argument);
            $newArguments[] = $newArgument;
        }

        if ($argumentsHaveChanged) {
            return new MethodCall($methodCall->getMethodName(), $newArguments);
        } else {
            return $methodCall;
        }
    }

    protected function visitUnary(Unary $unary)
    {
        $newExpression = $this->__invoke($unary->getExpression());

        if ($newExpression !== $unary->getExpression()) {
            return new Unary($newExpression, $unary->getOperation());
        } else {
            return $unary;
        }
    }

    /**
     * Processes an AST Expression
     *
     * @param Expression $expression expression to be processed
     * @return Expression processed expression
     */
    public function __invoke(Expression $expression)
    {
        $expression = call_user_func($this->callback, $expression);

        if ($expression instanceof Binary) {
            return $this->visitBinary($expression);
        } else if ($expression instanceof GetAttribute) {
            return $this->visitGetAttribute($expression);
        } else if ($expression instanceof MethodCall) {
            return $this->visitMethodCall($expression);
        } else {
            return $expression;
        }
    }
}
