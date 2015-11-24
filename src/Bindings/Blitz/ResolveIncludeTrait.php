<?php
namespace Plitz\Bindings\Blitz;

use Plitz\Lexer\Lexer;
use Plitz\Parser\Expressions;
use Plitz\Parser\Parser;

trait ResolveIncludeTrait
{
    protected $disableFunctionBoilerplate = false;

    public function startOfStream()
    {
        if (!$this->disableFunctionBoilerplate) {
            parent::startOfStream();
        }
    }

    public function endOfStream()
    {
        if (!$this->disableFunctionBoilerplate) {
            parent::endOfStream();
        }
    }

    protected function resolveIncludeCall(Expressions\MethodCall $expression)
    {
        assert($expression->getArguments()[0] instanceof Expressions\Scalar);
        $filename = $this->basePath . DIRECTORY_SEPARATOR . $expression->getArguments()[0]->getValue();
        $filehandle = fopen($filename, "r");
        assert(is_resource($filehandle));

        try {
            // make sure we don't write a new function header + footer
            $oldDisableFunctionBoilerPlate = $this->disableFunctionBoilerplate;
            $this->disableFunctionBoilerplate = true;

            $lexer = new Lexer($filehandle, $filename);
            $parser = new Parser($lexer->lex(), $this);

            $this->comment('@@START OF TEMPLATE ' . $filename . ' @@');
            $parser->parse();
            $this->comment('@@END OF TEMPLATE ' . $filename . ' @@');
        } finally {
            fclose($filehandle);
        }

        $this->disableFunctionBoilerplate = $oldDisableFunctionBoilerPlate;
    }
}
