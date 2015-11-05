<?php
namespace Plitz\Compilers;

use Plitz\Parser\Expression;
use Plitz\Parser\Expressions;
use Plitz\Parser\Visitor;

class BlitzCompiler implements Visitor
{
    /**
     * Output stream
     * @var resource
     */
    protected $output;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        $this->output = $stream;
    }

    public function startOfStream()
    {
        // do nothing
    }

    public function endOfStream()
    {
        // do nothing
    }

    public function raw($data)
    {
        fwrite($this->output, $data);
    }

    public function ifBlock(Expression $condition)
    {
        if ($condition instanceof Expressions\Unary && $condition->getOperation() === Expressions\Unary::OPERATION_NOT) {
            fwrite($this->output, '{{ UNLESS ');
            $condition = $condition->getExpression();
        } else {
            fwrite($this->output, '{{ IF ');
        }
        $this->expression($condition, true);
        fwrite($this->output, ' }}');
    }

    public function elseBlock()
    {
        fwrite($this->output, '{{ ELSE }}');
    }

    public function elseIfBlock(Expression $condition)
    {
        fwrite($this->output, '{{ ELSE IF ');
        $this->expression($condition, true);
        fwrite($this->output, ' }}');
    }

    public function endIfBlock()
    {
        fwrite($this->output, '{{ END }}');
    }

    public function loopBlock(Expression $variable)
    {
        fwrite($this->output, '{{ BEGIN ');
        $this->expression($variable, true);
        fwrite($this->output, ' }}');
    }

    public function endLoopBlock()
    {
        fwrite($this->output, '{{ END }}');
    }

    public function printBlock(Expression $value)
    {
        fwrite($this->output, '{{ ');
        $this->expression($value, true);
        fwrite($this->output, ' }}');
    }

    public function comment($data)
    {
        fwrite($this->output, '/* ' . $data . ' */');
    }

    protected function canParensBeOmittedFor(Expression $expr)
    {
        return $expr instanceof Expressions\Scalar ||
            $expr instanceof Expressions\MethodCall ||
            $expr instanceof Expressions\Variable;
    }

    protected function escapeScalar($variable)
    {
        return var_export($variable, true);
    }

    protected function expression(Expression $expr, $omitParens = false)
    {
        // TODO: implemente a more intelligent algorithm to decide whether or not to render parentheses

        if ($expr instanceof Expressions\Binary) {
            if (!$omitParens) {
                fwrite($this->output, "(");
            }
            $this->expression($expr->getLeft());
            fwrite($this->output, " " . $expr->getOperation() . " ");
            $this->expression($expr->getRight());
            if (!$omitParens) {
                fwrite($this->output, ")");
            }
        } else if ($expr instanceof Expressions\Unary) {
            $omitParens |= $this->canParensBeOmittedFor($expr->getExpression());
            fwrite($this->output, $expr->getOperation());
            if (!$omitParens) {
                fwrite($this->output, "(");
            }
            $this->expression($expr->getExpression());
            if (!$omitParens) {
                fwrite($this->output, ")");
            }
        } else if ($expr instanceof Expressions\MethodCall) {
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
            fwrite($this->output, '.' . $expr->getAttributeName());
        } else if ($expr instanceof Expressions\Variable) {
            fwrite($this->output, $expr->getVariableName());
        } else if ($expr instanceof Expressions\Scalar) {
            fwrite($this->output, $this->escapeScalar($expr->getValue()));
        } else {
            throw new \LogicException("Unknown expression class " . get_class($expr));
        }
    }
}
