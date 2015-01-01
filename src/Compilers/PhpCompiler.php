<?php
namespace Plitz\Compilers;

use Plitz\Parser\Expression;
use Plitz\Parser\Expressions;
use Plitz\Parser\Visitor;

class PhpCompiler implements Visitor
{
    /**
     * @var resource
     */
    private $output;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        $this->output = $stream;
    }

    public function raw($data)
    {
        fwrite($this->output, $data);
    }

    public function ifBlock(Expression $condition)
    {
        fwrite($this->output, '<?php if (');
        $this->expression($condition);
        fwrite($this->output, '): ?>');
    }

    public function elseBlock()
    {
        fwrite($this->output, '<?php else: ?>');
    }

    public function elseIfBlock(Expression $condition)
    {
        fwrite($this->output, '<?php else if (');
        $this->expression($condition);
        fwrite($this->output, '): ?>');
    }

    public function endIfBlock()
    {
        fwrite($this->output, '<?php endif; ?>');
    }

    public function loopBlock(Expression $variable)
    {
        fwrite($this->output, '<?php foreach (');
        $this->expression($variable);
        // FIXME
        fwrite($this->output, ' as $context): ?>');
    }

    public function endLoopBlock()
    {
        fwrite($this->output, '<?php endforeach; ?>');
    }

    public function printBlock(Expression $value)
    {
        fwrite($this->output, '<?=');
        $this->expression($value);
        fwrite($this->output, '?>');
    }

    private function canParensBeOmittedFor(Expression $expr)
    {
        return $expr instanceof Expressions\Scalar ||
            $expr instanceof Expressions\MethodCall ||
            $expr instanceof Expressions\Variable;
    }

    private function expression(Expression $expr)
    {
        if ($expr instanceof Expressions\Binary) {
            fwrite($this->output, "(");
            $this->expression($expr->getLeft());
            fwrite($this->output, " " . $expr->getOperation() . " ");
            $this->expression($expr->getRight());
            fwrite($this->output, ")");
        } else if ($expr instanceof Expressions\Unary) {
            $showParens = !$this->canParensBeOmittedFor($expr->getExpression());
            fwrite($this->output, $expr->getOperation());
            if ($showParens) {
                fwrite($this->output, "(");
            }
            $this->expression($expr->getExpression());
            if ($showParens) {
                fwrite($this->output, ")");
            }
        } else if ($expr instanceof Expressions\MethodCall) {
            // FIXME
            fwrite($this->output, $expr->getMethodName() . "(");
            $arguments = $expr->getArguments();
            for ($i=0; $i < count($arguments); $i++) {
                $this->expression($arguments[$i]);
                if ($i < count($arguments) - 1) {
                    fwrite($this->output, ", ");
                }
            }
            fwrite($this->output, ")");
        } else if ($expr instanceof Expressions\GetAttribute) {
            $this->expression($expr->getExpression());
            // FIXME
            fwrite($this->output, "['" . $expr->getAttributeName() . "']");
        } else if ($expr instanceof Expressions\Variable) {
            // FIXME
            fwrite($this->output, '$context[\'' . $expr->getVariableName() . '\']');
        } else if ($expr instanceof Expressions\Scalar) {
            fwrite($this->output, var_export($expr->getValue(), true));
        } else {
            throw new \LogicException("Unknown expression class " . get_class($expr));
        }
    }
}
