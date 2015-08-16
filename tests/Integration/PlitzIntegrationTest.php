<?php
namespace Plitz\Tests\Integration;

use Plitz\Bindings\Blitz\JsCompiler;
use Plitz\Bindings\Blitz\PhpCompiler;
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
        \Mockery::close();
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
     * Helper function which returns the path to the Node.js binary
     * @return string
     */
    private static function findNodeJsBinary()
    {
        static $binaries = ["node", "nodejs", "node.exe"];

        foreach ($binaries as $binary) {
            foreach (explode(PATH_SEPARATOR, getenv("PATH")) as $path) {
                $filename = $path . DIRECTORY_SEPARATOR . $binary;

                if (is_executable($filename)) {
                    return $filename;
                }
            }
        }

        return null;
    }

    /**
     * @param string $nodeJsPath
     * @param string $jsCode
     * @param array $context
     * @return string
     */
    protected function executeJsCode($nodeJsPath, $jsCode, array $context)
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $p = proc_open($nodeJsPath, $descriptorSpec, $pipes);
        if (!is_resource($p)) {
            throw new \RuntimeException("Could not start Node.js process");
        }

        try {
            stream_set_blocking($pipes[1], 0);
            stream_set_blocking($pipes[2], 0);

            // write JS code to process
            fwrite($pipes[0], "var templateFn = " . $jsCode . ";\n");
            fwrite($pipes[0], "var context = " . json_encode($context) . ";\n");
            fwrite($pipes[0], "var helpers = " . file_get_contents(__DIR__ . "/js_helpers.js") . ";");
            fwrite($pipes[0], "process.stdout.write(templateFn(helpers, context));\n");
            fclose($pipes[0]);

            // read output into memory buffers
            $stdout = '';
            $stderr = '';
            while (proc_get_status($p)['running']) {
                $fds = ['stdout' => $pipes[1], 'stderr' => $pipes[2]];
                $null = null;
                if (stream_select($fds, $null, $null, null, null) == 0) {
                    continue;
                }

                foreach ($fds as $name => $fd) {
                    ${$name} .= fread($fd, 8192);
                }
            }

            if (!empty($stderr)) {
                throw new \RuntimeException("Node.js gave an error: " . $stderr);
            }

            return $stdout;
        } finally {
            proc_close($p);
        }
    }

    /**
     * @dataProvider provideYamlCases
     * @param string $template
     * @param array $assignments
     * @param string $expectedOutput
     */
    public function testYamlCasesForPhp($template, array $assignments, $expectedOutput)
    {
        // write template to input stream
        fwrite($this->inputStream, $template);
        fseek($this->inputStream, 0, SEEK_SET);

        $blitzMock = \Mockery::mock('\\Plitz\\Bindings\\Blitz\\Blitz');
        $compiler = new PhpCompiler($this->outputStream, "notavailable://not-available", $blitzMock);
        $parser = new Parser($this->lexer->lex(), $compiler);
        $parser->parse();

        // rewind output stream and read its contents
        fseek($this->outputStream, 0, SEEK_SET);
        $phpCode = stream_get_contents($this->outputStream);

        // evaluate generated PHP code
        $output = $this->executePhpCode($phpCode, $assignments);

        $this->assertEquals(trim($expectedOutput), trim($output));
    }

    /**
     * @dataProvider provideYamlCases
     * @param string $template
     * @param array $assignments
     * @param string $expectedOutput
     */
    public function testYamlCasesForJs($template, array $assignments, $expectedOutput)
    {
        $nodeJsPath = self::findNodeJsBinary();
        if ($nodeJsPath === null) {
            $this->markTestSkipped("Could not find Node.js binary");
        }

        // write template to input stream
        fwrite($this->inputStream, $template);
        fseek($this->inputStream, 0, SEEK_SET);

        $compiler = new JsCompiler($this->outputStream, "notavailable://not-available");
        $parser = new Parser($this->lexer->lex(), $compiler);
        $parser->parse();

        // rewind output stream and read its contents
        fseek($this->outputStream, 0, SEEK_SET);
        $phpCode = stream_get_contents($this->outputStream);

        // evaluate generated JavaScript code
        $output = $this->executeJsCode($nodeJsPath, $phpCode, $assignments);

        $this->assertEquals(trim($expectedOutput), trim($output));
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
