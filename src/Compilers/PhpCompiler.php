<?php
namespace Plitz\Compilers;

use Plitz\Parser\Expression;
use Plitz\Parser\Expressions;
use Plitz\Parser\Visitor;

class PhpCompiler implements Visitor
{
    /**
     * Output stream
     * @var resource
     */
    protected $output;

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
     * Whether or not the last written string to the output stream ended in a '?>' PHP tag
     * @var boolean
     */
    private $previousOutputWasPhpTag;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        $this->output = $stream;
        $this->contextCounter = 0;
        $this->currentContext = 'context';
        $this->previousOutputWasPhpTag = false;
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
        if ($data[0] === "\n" && $this->previousOutputWasPhpTag) {
            // prepend $data with a newline emitted in PHP code, as the actual newline is omitted by the PHP engine
            // see these for more information:
            // http://stackoverflow.com/questions/1908175/is-there-a-way-to-force-a-new-line-after-php-closing-tag-when-embedded-among
            // http://php.net/manual/en/language.basic-syntax.instruction-separation.php
            $newlines = substr($data, 0, strcspn($data, "\n") + 1);
            $data = '<?="' . addcslashes($newlines, "\n") . '"?>' . $data;
        }

        fwrite($this->output, $data);
        $this->previousOutputWasPhpTag = false;
    }

    public function ifBlock(Expression $condition)
    {
        fwrite($this->output, '<?php if (');
        $this->expression($condition);
        fwrite($this->output, '): ?>');
        $this->previousOutputWasPhpTag = true;
    }

    public function elseBlock()
    {
        fwrite($this->output, '<?php else: ?>');
        $this->previousOutputWasPhpTag = true;
    }

    public function elseIfBlock(Expression $condition)
    {
        fwrite($this->output, '<?php elseif (');
        $this->expression($condition);
        fwrite($this->output, '): ?>');
        $this->previousOutputWasPhpTag = true;
    }

    public function endIfBlock()
    {
        fwrite($this->output, '<?php endif; ?>');
        $this->previousOutputWasPhpTag = true;
    }

    public function loopBlock(Expression $variable)
    {
        fwrite($this->output, '<?php foreach (');
        $this->expression($variable);

        $this->contextCounter++;
        $this->currentContext = ($this->contextCounter > 0 ? 'context' . $this->contextCounter : 'context');

        fwrite($this->output, ' as $' . $this->currentContext . '): ?>');
        $this->previousOutputWasPhpTag = true;
    }

    public function endLoopBlock()
    {
        fwrite($this->output, '<?php endforeach; ?>');
        $this->previousOutputWasPhpTag = true;

        $this->contextCounter--;
        $this->currentContext = ($this->contextCounter > 0 ? 'context' . $this->contextCounter : 'context');
    }

    public function printBlock(Expression $value)
    {
        fwrite($this->output, '<?=');
        $this->expression($value);
        fwrite($this->output, '?>');
        $this->previousOutputWasPhpTag = true;
    }

    public function comment($data)
    {
        fwrite($this->output, '<?php /* ' . $data . ' */ ?>');
        $this->previousOutputWasPhpTag = true;
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

    protected function expression(Expression $expr)
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
            fwrite($this->output, '[' . $this->escapeScalar($expr->getAttributeName()) . ']');
        } else if ($expr instanceof Expressions\Variable) {
            fwrite($this->output, '$' . $this->currentContext . '[' . $this->escapeScalar($expr->getVariableName()) . ']');
        } else if ($expr instanceof Expressions\Scalar) {
            fwrite($this->output, $this->escapeScalar($expr->getValue()));
        } else {
            throw new \LogicException("Unknown expression class " . get_class($expr));
        }
    }
}
