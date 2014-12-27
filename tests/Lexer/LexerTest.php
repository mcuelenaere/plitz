<?php
namespace Plitz\Tests\Lexer;

use Plitz\Lexer\Lexer;
use Symfony\Component\Yaml\Yaml;

class LexerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var resource
     */
    private $stream;

    /**
     * @var Lexer
     */
    private $lexer;

    protected function setUp()
    {
        $this->stream = fopen("php://temp", "r+");
        $this->lexer = new Lexer($this->stream);
    }

    protected function tearDown()
    {
        fclose($this->stream);
    }

    /**
     * @dataProvider provideYamlCases
     * @param string $message
     * @param array $expectedTokens
     */
    public function testYamlCases($message, array $expectedTokens)
    {
        fwrite($this->stream, $message);
        fseek($this->stream, 0, SEEK_SET);

        $lexStream = $this->lexer->lex();
        foreach ($lexStream as $index => $tokenArray) {
            list($token, $value) = $tokenArray;

            $this->assertArrayHasKey($index, $expectedTokens, "Unexpected token $token found");

            $this->assertEquals($expectedTokens[$index]['token'], $token, 'Token at line ' . $lexStream->getLine() . ' was not the same as expected');
            if (isset($expectedTokens[$index]['text'])) {
                $this->assertEquals($expectedTokens[$index]['text'], $value, 'Value at line ' . $lexStream->getLine() . ' was not the same as expected');
            }
        }
    }

    public function provideYamlCases()
    {
        foreach (glob(dirname(__FILE__) . "/LexerTestCases/*.yaml") as $file) {
            $parsed = Yaml::parse(file_get_contents($file), true);
            yield basename($file) => [
                $parsed['message'],
                $parsed['tokens']
            ];
        }
    }
}
