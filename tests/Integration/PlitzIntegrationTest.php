<?php
namespace Plitz\Tests\Integration;

use Plitz\Compilers\PhpCompiler;
use Plitz\Lexer\Lexer;
use Plitz\Parser\Parser;
use Symfony\Component\Yaml\Yaml;

class PlitzIntegrationTest extends \PHPUnit_Framework_TestCase
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

    /**
     * @var PhpCompiler
     */
    private $compiler;

    protected function setUp()
    {
        $this->inputStream = fopen("php://temp", "r+");
        $this->outputStream = fopen("php://temp", "r+");
        $this->lexer = new Lexer($this->inputStream);
        $this->compiler = new PhpCompiler($this->outputStream);
    }

    protected function tearDown()
    {
        fclose($this->inputStream);
        fclose($this->outputStream);
    }

    /**
     * @param string $phpCode
     * @param array $context
     * @return string
     */
    protected function executePhpCode($phpCode, array $context)
    {
        $function = create_function('$context', '?>' . $phpCode . '<?php return null;');

        ob_start();
        try {
            $function($context);
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * @dataProvider provideYamlCases
     * @param string $template
     * @param array $assignments
     * @param string $expectedOutput
     */
    public function testYamlCases($template, array $assignments, $expectedOutput)
    {
        // write template to input stream
        fwrite($this->inputStream, $template);
        fseek($this->inputStream, 0, SEEK_SET);

        $parser = new Parser($this->lexer->lex(), $this->compiler);
        $parser->parse();

        // rewind output stream and read its contents
        fseek($this->outputStream, 0, SEEK_SET);
        $phpCode = stream_get_contents($this->outputStream);

        // evaluate generated PHP code
        $output = $this->executePhpCode($phpCode, $assignments);

        $this->assertEquals($expectedOutput, $output);
    }

    public function provideYamlCases()
    {
        foreach (glob(dirname(__FILE__) . "/PlitzTestCases/*.yaml") as $file) {
            $parsed = Yaml::parse(file_get_contents($file), true);
            yield basename($file) => [
                $parsed['template'],
                $parsed['assignments'],
                $parsed['output']
            ];
        }
    }
}
