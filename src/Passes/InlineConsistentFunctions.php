<?php
namespace Plitz\Passes;

use Plitz\Parser\Expression;
use Plitz\Parser\Expressions\MethodCall;
use Plitz\Parser\Expressions\Scalar;

/**
 * Calls functions at build-time and inlines their output in the template's AST.
 */
class InlineConsistentFunctions implements ExpressionPass
{
    private $functions = [];

    /**
     * @param array $functions map of functions, with the function name as key and the callable function as value
     */
    public function __construct(array $functions)
    {
        $this->functions = $functions;
    }

    /**
     * @inheritdoc
     */
    public function __invoke(Expression $expression)
    {
        if (!($expression instanceof MethodCall)) {
            // not a function call
            return $expression;
        }

        if (!array_key_exists($expression->getMethodName(), $this->functions)) {
            // we don't know how to optimize this function
            return $expression;
        }

        // unpack arguments
        $arguments = [];
        foreach ($expression->getArguments() as $argument) {
            if (!($argument instanceof Scalar)) {
                // we currently only handle scalar arguments
                return $expression;
            }

            $arguments[] = $argument->getValue();
        }

        // call function
        $returnValue = call_user_func_array($this->functions[$expression->getMethodName()], $arguments);

        // inline return value
        return new Scalar($returnValue);
    }
}
