<?php
namespace Plitz\Tests\Lexer;

use Plitz\Lexer\Lexer;
use Plitz\Lexer\Tokens;

class LexerBufferUnderflowTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Lexer
     */
    private $lexer;

    /**
     * @var resource
     */
    private $stream;

    protected function setUp()
    {
        $this->stream = fopen("php://temp", "r+");
        $this->lexer = new Lexer($this->stream, 'n/a', 10);
    }

    protected function tearDown()
    {
        fclose($this->stream);
    }

    /**
     * @dataProvider provideWhitespace
     * @argument int $amountOfWhitespace
     */
    public function testWhitespace($amountOfPrecedingWhitespace, $amountOfTrailingWhitespace)
    {
        $expectedTokens = [Tokens::T_LITERAL, Tokens::T_OPEN_PAREN, Tokens::T_LITERAL, Tokens::T_CLOSE_PAREN];
        if ($amountOfPrecedingWhitespace > 0) {
            array_unshift($expectedTokens, Tokens::T_RAW);
        }
        if ($amountOfTrailingWhitespace > 0) {
            array_push($expectedTokens, Tokens::T_RAW);
        }

        $text = str_repeat(" ", $amountOfPrecedingWhitespace) . '{{ foo(bar) }}' . str_repeat(" ", $amountOfTrailingWhitespace);
        fwrite($this->stream, $text);
        fseek($this->stream, 0, SEEK_SET);

        // shouldn't throw a syntax error
        $tokens = iterator_to_array($this->lexer->lex());
        $this->assertEquals($expectedTokens, array_map('reset', $tokens));
    }

    public function provideWhitespace()
    {
        return [
            [20,  0],
            [19,  0],
            [18,  0],
            [17,  0],
            [11,  0],
            [10,  0],
            [9,   0],
            [8,   0],
            [0,  11],
            [0,  10],
            [0,   9],
            [0,   8],
        ];
    }
}
