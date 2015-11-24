<?php
namespace Plitz\Tests\Compilers;

use Plitz\Compilers\BlitzCompiler;
use Plitz\Compilers\JsCompiler;
use Plitz\Compilers\PhpCompiler;
use Plitz\Lexer\Lexer;
use Plitz\Parser\Parser;
use Symfony\Component\Yaml\Yaml;

class CompilerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var resource
     */
    private $inputStream;

    /**
     * @var resource
     */
    private $outputStream;

    /**
     * @var Lexer
     */
    private $lexer;

    protected function setUp()
    {
        $this->inputStream = fopen("php://temp", "r+");
        $this->outputStream = fopen("php://temp", "r+");
        $this->lexer = new Lexer($this->inputStream);
    }

    protected function tearDown()
    {
        fclose($this->inputStream);
        fclose($this->outputStream);
    }

    /**
     * @dataProvider provideYamlCases
     * @param string $language
     * @param string $template
     * @param string|null $expectedOutput
     * @param string|null $expectedExceptionMessage
     */
    public function testYamlCases($language, $template, $expectedOutput, $expectedExceptionMessage = null)
    {
        // write template to input stream
        fwrite($this->inputStream, $template);
        fseek($this->inputStream, 0, SEEK_SET);

        switch ($language) {
            case 'JS':
                $compiler = new JsCompiler($this->outputStream, "    ");
                break;
            case 'PHP':
                $compiler = new PhpCompiler($this->outputStream);
                break;
            case 'Blitz':
                $compiler = new BlitzCompiler($this->outputStream);
                break;
            default:
                throw new \InvalidArgumentException('Invalid language "' . $language . '" given');
        }

        $parser = new Parser($this->lexer->lex(), $compiler);
        try {
            $parser->parse();
        } catch (\Exception $ex) {
            if ($expectedExceptionMessage !== null) {
                $this->assertStringMatchesFormat($expectedExceptionMessage, $ex->getMessage());
            } else {
                throw $ex;
            }
        }

        if ($expectedOutput !== null) {
            // rewind output stream
            fseek($this->outputStream, 0, SEEK_SET);

            $this->assertEquals($expectedOutput, stream_get_contents($this->outputStream));
        }
    }

    public function provideYamlCases()
    {
        foreach (glob(dirname(__FILE__) . "/CompilerTestCases/*.yaml") as $file) {
            $parsed = Yaml::parse(file_get_contents($file), true);
            yield basename($file) => [
                $parsed['language'],
                $parsed['template'],
                isset($parsed['output']) ? $parsed['output'] : null,
                isset($parsed['exception']) ? $parsed['exception'] : null
            ];
        }
    }
}
