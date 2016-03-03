<?php
namespace Plitz\Bindings\Blitz;

use Plitz\Expressions\ExpressionProcessor;
use Plitz\Lexer\Lexer;
use Plitz\Parser\Expression;
use Plitz\Parser\Expressions;
use Plitz\Parser\Parser;
use Plitz\Passes\PreventScopeEscape;

trait ResolveIncludeTrait
{
    protected $inIncludeStatement = false;

    public function startOfStream()
    {
        if (!$this->inIncludeStatement) {
            parent::startOfStream();
        }
    }

    public function endOfStream()
    {
        if (!$this->inIncludeStatement) {
            parent::endOfStream();
        }
    }

    private $expressionProcessor = null;

    protected function expression(Expression $expr)
    {
        if (!$this->inIncludeStatement) {
            return parent::expression($expr);
        }

        if ($this->expressionProcessor === null) {
            $this->expressionProcessor = new ExpressionProcessor(new PreventScopeEscape());
        }

        return call_user_func($this->expressionProcessor, $expr);
    }

    private function transformExpressionToLookupInParent(Expression $expression)
    {
        if ($expression instanceof Expressions\Variable) {
            return new Expressions\GetAttribute(new Expressions\Variable('_parent'), $expression->getVariableName());
        } else if ($expression instanceof Expressions\GetAttribute) {
            // build up stack of attributes
            $stack = [];
            while ($expression instanceof Expressions\GetAttribute) {
                $stack[] = $expression->getAttributeName();
                $expression = $expression->getExpression();
            }

            if (!$expression instanceof Expressions\Variable) {
                throw new \UnexpectedValueException("GetAttribute expression should only be possible on a Variable expression");
            }

            // add the inner variable as an attribute as well
            $stack[] = $expression->getVariableName();

            // unwind stack again
            $expression = new Expressions\Variable('_parent');
            foreach (array_reverse($stack) as $attributeName) {
                $expression = new Expressions\GetAttribute($expression, $attributeName);
            }
            return $expression;
        } else if ($expression instanceof Expressions\Scalar) {
            return $expression;
        }

        throw new \UnexpectedValueException("Include scope can only be overridden by simple expressions (variable or scalar)");
    }

    protected function resolveIncludeCall(Expressions\ScopedInclude $expression)
    {
        assert($expression->getIncluded() instanceof Expressions\Scalar);
        $filename = $this->basePath . DIRECTORY_SEPARATOR . $expression->getIncluded()->getValue();
        $filehandle = fopen($filename, "r");
        assert(is_resource($filehandle));

        try {
            // inject scope into included template
            foreach ($expression->getScope() as $key => $expression) {
                // transform expression to be looked-up in its parent
                $expression = $this->transformExpressionToLookupInParent($expression);

                $this->printBlock(new Expressions\MethodCall("set", [new Expressions\Scalar($key), $expression]));
            }

            // make sure we don't write a new function header + footer
            $oldInIncludeStatement = $this->inIncludeStatement;
            $this->inIncludeStatement = true;

            $lexer = new Lexer($filehandle, $filename);
            $parser = new Parser($lexer->lex(), $this);

            $this->comment('@@START OF TEMPLATE ' . $filename . ' @@');
            $parser->parse();
            $this->comment('@@END OF TEMPLATE ' . $filename . ' @@');
        } finally {
            fclose($filehandle);
        }

        $this->inIncludeStatement = $oldInIncludeStatement;
    }
}
