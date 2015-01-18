<?php
namespace Plitz\Bindings\Blitz;

use Plitz\Parser\Expression;
use Plitz\Parser\Expressions;

class PhpCompiler extends \Plitz\Compilers\PhpCompiler
{
    use EscapeExpressionTrait;
    use ResolveIncludeTrait;
    use ResolveParentVariableTrait;
    use ConvertGetAttributeToFetchPropertyCallTrait;

    /**
     * @var string
     */
    private $basePath;

    /**
     * @var Blitz
     */
    private $blitz;

    public function __construct($stream, $basePath, Blitz $blitz)
    {
        parent::__construct($stream);

        $this->basePath = $basePath;
        $this->blitz = $blitz;
    }

    public function printBlock(Expression $value)
    {
        if ($value instanceof Expressions\MethodCall && $value->getMethodName() === 'assignVar') {
            fwrite($this->output, '<?php $' . $this->currentContext . '[');
            $this->expression($value->getArguments()[0]);
            fwrite($this->output, '] = ');
            $this->expression($value->getArguments()[1]);
            fwrite($this->output, '; ?>');
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
                fwrite($this->output, '$' . $context);
                return;
            } else if ($expr->getVariableName() === '_top') {
                fwrite($this->output, '$context');
                return;
            } else if ($expr->getVariableName() === '_') {
                fwrite($this->output, '$' . $this->currentContext);
                return;
            }
        } else if ($expr instanceof Expressions\GetAttribute) {
            if ($expr->getAttributeName() === '_parent') {
                $depth = $this->resolveParentVariable($expr);

                $contextCounter = max(0, $this->contextCounter - $depth);
                $context = ($contextCounter > 0 ? 'context' . $contextCounter : 'context');
                fwrite($this->output, '$' . $context);
                return;
            } else {
                $expr = $this->convertGetAttributeToFetchPropertyCall($expr);
            }
        } else if ($expr instanceof Expressions\MethodCall) {
            // TODO: resolve method calls
            if ($expr->getMethodName() === 'if') {
                fwrite($this->output, '(');
                $this->expression($expr->getArguments()[0]);
                fwrite($this->output, ' ? ');
                $this->expression($expr->getArguments()[1]);
                fwrite($this->output, ' : ');
                if (count($expr->getArguments()) === 3) {
                    $this->expression($expr->getArguments()[2]);
                } else {
                    fwrite($this->output, 'null');
                }
                fwrite($this->output, ')');
                return;
            } else if ($expr->getMethodName() === 'escape') {
                $expr = new Expressions\MethodCall('htmlentities', [
                    $expr->getArguments()[0],
                    new Expressions\Scalar(ENT_QUOTES),
                    new Expressions\Scalar(ini_get('default_charset'))
                ]);
            } else {
                // TODO: support configurable method lookup order

                // resolve method call
                if (method_exists($this->blitz, $expr->getMethodName())) {
                    // this is a Blitz call, use $blitz variable
                    fwrite($this->output, '$blitz->');
                } else if (function_exists($expr->getMethodName())) {
                    // this is a regular PHP call, do nothing
                } else {
                    throw new \RuntimeException("Function {$expr->getMethodName()} could not be resolved");
                }
            }
        }

        parent::expression($expr);
    }
}
