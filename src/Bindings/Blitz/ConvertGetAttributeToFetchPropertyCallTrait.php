<?php
namespace Plitz\Bindings\Blitz;

use Plitz\Parser\Expressions;

trait ConvertGetAttributeToFetchPropertyCallTrait
{
    /**
     * This method reduces a (recursive) GetAttribute expression to a TemplateFunctions::fetchProperty() MethodCall expression
     * @param Expressions\GetAttribute $expr
     * @return Expressions\MethodCall
     */
    protected function convertGetAttributeToFetchPropertyCall(Expressions\GetAttribute $expr)
    {
        $properties = [
            $expr->getExpression()
        ];

        while ($expr instanceof Expressions\GetAttribute) {
            $properties[] = new Expressions\Scalar($expr->getAttributeName());
            $expr = $expr->getExpression();
        }

        return new Expressions\MethodCall('\\Plitz\\Bindings\\Blitz\\TemplateFunctions::fetchProperty', $properties);
    }
}
