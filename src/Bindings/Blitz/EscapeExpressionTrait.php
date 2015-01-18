<?php
namespace Plitz\Bindings\Blitz;

use Plitz\Parser\Expression;
use Plitz\Parser\Expressions;

trait EscapeExpressionTrait
{
    protected function escapeExpression(Expression $expr)
    {
        // check if we should _not_ escape this expression
        if ($expr instanceof Expressions\MethodCall && $expr->getMethodName() === 'raw') {
            return $expr->getArguments()[0];
        }

        // check if $expr is already wrapped in an escape call
        if ($expr instanceof Expressions\MethodCall && $expr->getMethodName() === 'escape') {
            return $expr;
        }

        // TODO: remove this hack
        if ($expr instanceof Expressions\MethodCall) {
            return $expr;
        }

        // binary expression either return a boolean or a numeric value, so they should never be escaped
        if ($expr instanceof Expressions\Binary) {
            return $expr;
        }

        // TODO: simple expressions can be escaped at compile time

        // wrap in an escape call
        return new Expressions\MethodCall('escape', [$expr]);
    }
}
