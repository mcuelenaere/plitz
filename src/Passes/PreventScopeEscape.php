<?php
namespace Plitz\Passes;

use Plitz\Parser\Expression;
use Plitz\Parser\Expressions;

/**
 * Ensures an expression cannot escape its current scope by replacing all references to _top/_parent to the current scope
 */
class PreventScopeEscape implements ExpressionPass
{
    /**
     * @inheritdoc
     */
    public function __invoke(Expression $expression)
    {
        if ($expression instanceof Expressions\Variable) {
            switch ($expression->getVariableName()) {
                case '_top':
                case '_parent':
                    return new Expressions\Variable('_');
            }
        } else if ($expression instanceof Expressions\GetAttribute) {
            switch ($expression->getAttributeName()) {
                case '_top':
                case '_parent':
                    return new Expressions\GetAttribute($expression->getExpression(), '_');
            }
        }

        return $expression;
    }
}
