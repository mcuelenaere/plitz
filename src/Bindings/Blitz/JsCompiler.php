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
     * Wraps the expression in a `!helpers.isEmpty()` call, if necessary
     *
     * This is necessary because JavaScript behaves differently than PHP when evaluating non-boolean values for their
     * truthiness; eg:
     *
     * node -e 'console.log(!![])'    true
     * php -r 'var_dump(!![]);'       bool(false)
     *
     * node -e 'console.log(!!"0")'   true
     * php -r 'var_dump(!!"0");'      bool(false)
     *
     * @param Expression $expr
     * @return Expression
     */
    protected function wrapExpressionInIsNotEmptyCall(Expression $expr)
    {
        if ($expr instanceof Expressions\Binary) {
            $shouldWrap = self::expressionRequiresBooleanInput($expr);

            // check left and right sub-expressions
            $left = ($shouldWrap || self::expressionRequiresBooleanInput($expr->getLeft()))
                ? $this->wrapExpressionInIsNotEmptyCall($expr->getLeft())
                : $expr->getLeft();
            $right = ($shouldWrap || self::expressionRequiresBooleanInput($expr->getRight()))
                ? $this->wrapExpressionInIsNotEmptyCall($expr->getRight())
                : $expr->getRight();

            if ($left !== $expr->getLeft() || $right !== $expr->getRight()) {
                $expr = new Expressions\Binary($left, $right, $expr->getOperation());
            }
        } else if ($expr instanceof Expressions\Unary) {
            $shouldWrap = self::expressionRequiresBooleanInput($expr);

            // check sub-expression
            $subExpr = ($shouldWrap || self::expressionRequiresBooleanInput($expr->getExpression()))
                ? $this->wrapExpressionInIsNotEmptyCall($expr->getExpression())
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
            $isEmpty = !empty($expr->getValue());
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
        $condition = $this->wrapExpressionInIsNotEmptyCall($condition);

        parent::ifBlock($condition);
    }

    public function elseIfBlock(Expression $condition)
    {
        $condition = $this->wrapExpressionInIsNotEmptyCall($condition);

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
        } else if ($expr instanceof Expressions\GetAttribute && $expr->getAttributeName() === '_parent') {
            $depth = $this->resolveParentVariable($expr);

            $contextCounter = max(0, $this->contextCounter - $depth);
            $context = ($contextCounter > 0 ? 'context' . $contextCounter : 'context');
            $this->codeEmitter->raw($context);
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
