<?php
namespace Plitz\Passes;

use Plitz\Parser\Expression;

interface ExpressionPass
{
    /**
     * Processes an expression
     *
     * @param Expression $expression expression to be processed
     * @return Expression processed expression
     */
    public function __invoke(Expression $expression);
}
