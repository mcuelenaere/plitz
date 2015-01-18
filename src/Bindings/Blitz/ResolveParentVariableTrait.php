<?php
namespace Plitz\Bindings\Blitz;

use Plitz\Parser\Expressions;

trait ResolveParentVariableTrait
{
    protected function resolveParentVariable(Expressions\GetAttribute $expr)
    {
        $depth = 0;
        while ($expr instanceof Expressions\GetAttribute && $expr->getAttributeName() === '_parent') {
            $depth++;
            $expr = $expr->getExpression();
        }

        if (!$expr instanceof Expressions\Variable || $expr->getVariableName() !== '_parent') {
            throw new \InvalidArgumentException("You cannot do a `_parent` dereference on anything but `_parent`!");
        }
        $depth++;

        return $depth;
    }
}
