<?php
namespace Plitz\Compilers;

use Plitz\Parser\Expression;
use Plitz\Parser\Expressions;
use Plitz\Parser\Visitor;

class JsCompiler implements Visitor
{
    /**
     * Code emitter
     * @var CodeEmitter
     */
    protected $codeEmitter;

    /**
     * Variable name of the current context
     * @var string
     */
    protected $currentContext;

    /**
     * Current context counter
     * @var int
     */
    protected $contextCounter;

    /**
     * @param resource $stream
     * @param string $indentationCharacter
     */
    public function __construct($stream, $indentationCharacter = "\t")
    {
        $this->codeEmitter = new CodeEmitter($stream);
        $this->codeEmitter->setIdentationCharacter($indentationCharacter);
        $this->contextCounter = 0;
        $this->currentContext = 'context';
    }

    public function startOfStream()
    {
        $this->codeEmitter
            ->line("function (helpers, data) {")
            ->indent()
            ->line("var context = data || {};")
            ->line("var buffer = '';")
            ->newline();
    }

    public function endOfStream()
    {
        $this->codeEmitter
            ->line("return buffer;")
            ->outdent()
            ->line("}");
    }

    public function raw($data)
    {
        $this->codeEmitter
            ->line("buffer += " . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";");
    }

    public function ifBlock(Expression $condition)
    {
        $this->codeEmitter->lineNoEnding('if (');
        $this->expression($condition);
        $this->codeEmitter
            ->raw(') {')
            ->newline()
            ->indent();
    }

    public function elseBlock()
    {
        $this->codeEmitter
            ->outdent()
            ->line('} else {')
            ->indent();
    }

    public function elseIfBlock(Expression $condition)
    {
        $this->codeEmitter
            ->outdent()
            ->lineNoEnding('} else if (');
        $this->expression($condition);
        $this->codeEmitter
            ->raw(') {')
            ->newline()
            ->indent();
    }

    public function endIfBlock()
    {
        $this->codeEmitter
            ->outdent()
            ->line('}');
    }

    public function loopBlock(Expression $variable)
    {
        $loopVariable = "i" . $this->contextCounter;
        $newContext = 'context' . ($this->contextCounter + 1);

        $this->codeEmitter
            ->newline()
            ->lineNoEnding("for (var {$loopVariable} = 0; {$loopVariable} < ");
        $this->expression($variable);
        $this->codeEmitter
            ->raw(".length; {$loopVariable}++) {")
            ->newline()
            ->indent()
            ->lineNoEnding("var {$newContext} = ");

        $this->expression($variable);

        $this->codeEmitter
            ->raw("[{$loopVariable}];")
            ->newline()
            ->newline();

        $this->contextCounter++;
        $this->currentContext = $newContext;
    }

    public function endLoopBlock()
    {
        $this->codeEmitter
            ->outdent()
            ->line("}")
            ->newline();

        $this->contextCounter--;
        $this->currentContext = ($this->contextCounter > 0 ? 'context' . $this->contextCounter : 'context');
    }

    public function printBlock(Expression $value)
    {
        $this->codeEmitter
            ->lineNoEnding("buffer += ");
        $this->expression($value);
        $this->codeEmitter
            ->raw(";")
            ->newline();
    }

    protected function canParensBeOmittedFor(Expression $expr)
    {
        return $expr instanceof Expressions\Scalar ||
        $expr instanceof Expressions\MethodCall ||
        $expr instanceof Expressions\Variable;
    }

    protected function escapeScalar($variable)
    {
        return json_encode($variable, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function createJsVariableDereference($attributeName)
    {
        $escaped = $this->escapeScalar($attributeName);
        if (substr($escaped, 1, -1) === $attributeName) {
            return '.' . $attributeName;
        } else {
            return '[' . $escaped . ']';
        }
    }

    protected function expression(Expression $expr)
    {
        if ($expr instanceof Expressions\Binary) {
            $this->codeEmitter->raw('(');
            $this->expression($expr->getLeft());
            $this->codeEmitter->raw(' ' . $expr->getOperation() . ' ');
            $this->expression($expr->getRight());
            $this->codeEmitter->raw(')');
        } else if ($expr instanceof Expressions\Unary) {
            $showParens = !$this->canParensBeOmittedFor($expr->getExpression());
            $this->codeEmitter->raw($expr->getOperation());
            if ($showParens) {
                $this->codeEmitter->raw('(');
            }
            $this->expression($expr->getExpression());
            if ($showParens) {
                $this->codeEmitter->raw(')');
            }
        } else if ($expr instanceof Expressions\MethodCall) {
            $this->codeEmitter->raw('helpers' . $this->createJsVariableDereference($expr->getMethodName()) . '(');
            $arguments = $expr->getArguments();
            for ($i=0; $i < count($arguments); $i++) {
                $this->expression($arguments[$i]);
                if ($i < count($arguments) - 1) {
                    $this->codeEmitter->raw(', ');
                }
            }
            $this->codeEmitter->raw(')');
        } else if ($expr instanceof Expressions\GetAttribute) {
            $this->expression($expr->getExpression());
            $this->codeEmitter->raw($this->createJsVariableDereference($expr->getAttributeName()));
        } else if ($expr instanceof Expressions\Variable) {
            $this->codeEmitter->raw($this->currentContext . $this->createJsVariableDereference($expr->getVariableName()));
        } else if ($expr instanceof Expressions\Scalar) {
            $this->codeEmitter->raw($this->escapeScalar($expr->getValue()));
        } else {
            throw new \LogicException("Unknown expression class " . get_class($expr));
        }
    }
}
