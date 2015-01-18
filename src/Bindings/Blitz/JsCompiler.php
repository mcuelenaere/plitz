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
