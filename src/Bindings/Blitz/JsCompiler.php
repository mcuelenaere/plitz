<?php
namespace Plitz\Bindings\Blitz;

use Plitz\Parser\Expression;
use Plitz\Parser\Expressions;

class JsCompiler extends \Plitz\Compilers\JsCompiler
{
    use EscapeExpressionTrait;
    use ResolveIncludeTrait;
    use ResolveParentVariableTrait;

    private $basePath;

    public function __construct($stream, $basePath)
    {
        parent::__construct($stream);

        $this->basePath = $basePath;
    }

    /**
     * Helper function which returns true if the expression requires boolean input values
     *
     * @param Expression $expr
     * @return bool
     */
    protected static function expressionRequiresBooleanInput(Expression $expr)
    {
        return (
            ($expr instanceof Expressions\Binary && in_array($expr->getOperation(), [Expressions\Binary::OPERATOR_AND, Expressions\Binary::OPERATOR_OR])) ||
            ($expr instanceof Expressions\Unary && $expr->getOperation() === Expressions\Unary::OPERATION_NOT)
        );
    }

    /**
     * Wraps the expression in a `helpers.isEmpty()` call, if necessary
     *
     * @param Expression $expr
     * @return Expression
     */
    protected function wrapExpressionInIsEmptyCall(Expression $expr)
    {
        if ($expr instanceof Expressions\Binary) {
            $shouldWrap = self::expressionRequiresBooleanInput($expr);

            // check left and right sub-expressions
            $left = ($shouldWrap || self::expressionRequiresBooleanInput($expr->getLeft()))
                ? $this->wrapExpressionInIsEmptyCall($expr->getLeft())
                : $expr->getLeft();
            $right = ($shouldWrap || self::expressionRequiresBooleanInput($expr->getRight()))
                ? $this->wrapExpressionInIsEmptyCall($expr->getRight())
                : $expr->getRight();

            if ($left !== $expr->getLeft() || $right !== $expr->getRight()) {
                $expr = new Expressions\Binary($left, $right, $expr->getOperation());
            }
        } else if ($expr instanceof Expressions\Unary) {
            $shouldWrap = self::expressionRequiresBooleanInput($expr);

            // check sub-expression
            $subExpr = ($shouldWrap || self::expressionRequiresBooleanInput($expr->getExpression()))
                ? $this->wrapExpressionInIsEmptyCall($expr->getExpression())
                : $expr->getExpression();

            if ($subExpr !== $expr->getExpression()) {
                $expr = new Expressions\Unary($subExpr, $expr->getOperation());

                // check if we have a double nested unary not operator
                if (
                    $expr->getOperation() === Expressions\Unary::OPERATION_NOT &&
                    $expr->getExpression() instanceof Expressions\Unary &&
                    $expr->getExpression()->getOperation() === Expressions\Unary::OPERATION_NOT
                ) {
                    // unwrap the nested expression
                    $expr = $expr->getExpression()->getExpression();
                }
            }
        } else if ($expr instanceof Expressions\Scalar) {
            // can be calculated at build-time
            $isEmpty = empty($expr->getValue());
            $expr = new Expressions\Scalar($isEmpty);
        } else if (
            ($expr instanceof Expressions\GetAttribute) ||
            ($expr instanceof Expressions\MethodCall) ||
            ($expr instanceof Expressions\Variable)
        ) {
            // wrap potential empty value in a `!helpers.isEmpty($)` call
            $expr = new Expressions\Unary(
                new Expressions\MethodCall(
                    'isEmpty',
                    [$expr]
                ),
                Expressions\Unary::OPERATION_NOT
            );
        }

        return $expr;
    }

    public function ifBlock(Expression $condition)
    {
        $condition = $this->wrapExpressionInIsEmptyCall($condition);

        parent::ifBlock($condition);
    }

    public function elseIfBlock(Expression $condition)
    {
        $condition = $this->wrapExpressionInIsEmptyCall($condition);

        parent::elseIfBlock($condition);
    }

    public function printBlock(Expression $value)
    {
        if ($value instanceof Expressions\MethodCall && $value->getMethodName() === 'assignVar') {
            $this->codeEmitter->lineNoEnding($this->currentContext . '[');
            $this->expression($value->getArguments()[0]);
            $this->codeEmitter->raw('] = ');
            $this->expression($value->getArguments()[1]);
            $this->codeEmitter->raw(';')->newline();
            return;
        } else if ($value instanceof Expressions\MethodCall && $value->getMethodName() === 'include') {
            $this->resolveIncludeCall($value);
            return;
        }

        parent::printBlock($this->escapeExpression($value));
    }

    /**
     * This method reduces a (recursive) GetAttribute expression to a chain of binary `&&` expressions
     * @param Expressions\GetAttribute $expr
     * @return array
     */
    protected function convertGetAttributeToFetchPropertyCall(Expressions\GetAttribute $expr)
    {
        $baseExpr = $expr->getExpression();
        if (
            $baseExpr instanceof Expressions\Variable &&
            !in_array($baseExpr->getVariableName(), ['_parent', '_top', '_'])
        ) {
            $properties = [
                $baseExpr->getVariableName()
            ];
            $baseExpr = new Expressions\Variable('_');
        } else {
            $properties = [];
        }

        while ($expr instanceof Expressions\GetAttribute) {
            if ($expr->getAttributeName() === '_parent') {
                if (count($properties) > 0) {
                    array_pop($properties);
                } else {
                    $baseExpr = new Expressions\GetAttribute($baseExpr, '_parent');
                }
            } else {
                $properties[] = $expr->getAttributeName();
            }

            $expr = $expr->getExpression();
        }

        return [$baseExpr, $properties];
    }

    protected function expression(Expression $expr)
    {
        if ($expr instanceof Expressions\Variable) {
            if ($expr->getVariableName() === '_parent') {
                $contextCounter = max(0, $this->contextCounter - 1);
                $context = ($contextCounter > 0 ? 'context' . $contextCounter : 'context');
                $this->codeEmitter->raw($context);
            } else if ($expr->getVariableName() === '_top') {
                $this->codeEmitter->raw('context');
            } else if ($expr->getVariableName() === '_') {
                $this->codeEmitter->raw($this->currentContext);
            } else {
                parent::expression($expr);
            }
        } else if ($expr instanceof Expressions\GetAttribute) {
            if ($expr->getAttributeName() === '_parent') {
                $depth = $this->resolveParentVariable($expr);

                $contextCounter = max(0, $this->contextCounter - $depth);
                $context = ($contextCounter > 0 ? 'context' . $contextCounter : 'context');
                $this->codeEmitter->raw($context);
            } else {
                list($baseExpr, $properties) = $this->convertGetAttributeToFetchPropertyCall($expr);

                // print chain of `&&` binary operators
                $this->codeEmitter->raw('(');
                $this->expression($baseExpr);
                for ($i = 0; $i < count($properties); $i++) {
                    $this->codeEmitter->raw(' && ');

                    $this->expression($baseExpr);
                    for ($j = 0; $j < $i; $j++) {
                        $this->codeEmitter->raw($this->createJsVariableDereference($properties[$j]));
                    }
                    $this->codeEmitter->raw($this->createJsVariableDereference($properties[$i]));
                }
                $this->codeEmitter->raw(')');
            }
        } else if ($expr instanceof Expressions\MethodCall && $expr->getMethodName() === 'if') {
            $this->codeEmitter->raw('(');
            $this->expression($expr->getArguments()[0]);
            $this->codeEmitter->raw(' ? ');
            $this->expression($expr->getArguments()[1]);
            $this->codeEmitter->raw(' : ');
            if (count($expr->getArguments()) === 3) {
                $this->expression($expr->getArguments()[2]);
            } else {
                $this->codeEmitter->raw('\"\"');
            }
            $this->codeEmitter->raw(')');
        } else {
            parent::expression($expr);
        }
    }
}
