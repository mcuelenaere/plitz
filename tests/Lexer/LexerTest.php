<?php
namespace Plitz\Tests\Lexer;

use Plitz\Lexer\Lexer;
use Plitz\Lexer\LexException;
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
     * @param array $flow
     */
    public function testYamlCases($message, array $flow)
    {
        fwrite($this->stream, $message);
        fseek($this->stream, 0, SEEK_SET);

        $index = 0;
        $lexStream = $this->lexer->lex();
        try {
            foreach ($lexStream as $tokenArray) {
                list($token, $value) = $tokenArray;

                $this->assertArrayHasKey($index, $flow, "Unexpected token $token found");

                $this->assertEquals($flow[$index]['token'], $token, 'Token at line ' . $lexStream->getLine() . ' was not the same as expected');
                if (isset($flow[$index]['text'])) {
                    $this->assertEquals($flow[$index]['text'], $value, 'Value at line ' . $lexStream->getLine() . ' was not the same as expected');
                }
                $index++;
            }
        } catch (LexException $ex) {
            if (isset($flow[$index]) && isset($flow[$index]['exception'])) {
                $this->assertEquals($flow[$index]['exception'], $ex->getMessage());
            } else {
                throw $ex;
            }
        }
    }

    public function provideYamlCases()
    {
        foreach (glob(dirname(__FILE__) . "/LexerTestCases/*.yaml") as $file) {
            $parsed = Yaml::parse(file_get_contents($file), true);
            yield basename($file) => [
                $parsed['message'],
                $parsed['flow']
            ];
        }
    }
}
