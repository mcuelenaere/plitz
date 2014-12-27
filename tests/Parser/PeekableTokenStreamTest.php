<?php
namespace Plitz\Tests\Parser;

use Plitz\Lexer\Tokens;
use Plitz\Lexer\TokenStream;
use Plitz\Parser\PeekableTokenStream;

class PeekableTokenStreamTest extends \PHPUnit_Framework_TestCase
{
    protected function tearDown()
    {
        \Mockery::close();
    }

    /**
     * @param array $tokens
     * @return TokenStream
     */
    private function createArrayTokenStream(array $tokens)
    {
        $tokenStream = \Mockery::mock('\\Plitz\\Tests\\Lexer\\MockableTokenstream[getLine,getSource]', '\\ArrayIterator', [$tokens]);

        $tokenStream
            ->shouldDeferMissing();

        $tokenStream
            ->shouldReceive('getLine')
            ->zeroOrMoreTimes()
            ->andReturn(0);

        $tokenStream
            ->shouldReceive('getSource')
            ->zeroOrMoreTimes()
            ->andReturn('n/a');

        return $tokenStream;
    }

    public function testArrayOfTokens()
    {
        $tokens = [Tokens::T_RAW, Tokens::T_BLOCK_BEGIN];
        $tokenStream = $this->createArrayTokenStream($tokens);
        $peekableTokenStream = new PeekableTokenStream($tokenStream);

        $i = 0;
        foreach ($peekableTokenStream as $token) {
            $this->assertLessThan(count($tokens), $i);

            $this->assertEquals($tokens[$i], $token);
            if ($i + 1 < count($tokens)) {
                $this->assertEquals($tokens[$i + 1], $peekableTokenStream->peek());
            } else {
                $this->assertNull($peekableTokenStream->peek());
            }

            $i++;
        }

        $this->assertEquals(count($tokens), $i);
    }

    public function testEmptyIterator()
    {
        $tokenStream = $this->createArrayTokenStream([]);
        $peekableTokenStream = new PeekableTokenStream($tokenStream);

        $peekableTokenStream->rewind();
        $this->assertEquals(false, $peekableTokenStream->valid());
        $this->assertNull($peekableTokenStream->peek());
    }

    public function testOverdrawnIterator()
    {
        $tokenStream = $this->createArrayTokenStream(['foo']);
        $peekableTokenStream = new PeekableTokenStream($tokenStream);

        $peekableTokenStream->rewind();
        $this->assertEquals(true, $peekableTokenStream->valid());
        $this->assertEquals('foo', $peekableTokenStream->current());

        $peekableTokenStream->next();
        $this->assertEquals(false, $peekableTokenStream->valid());
        $this->assertNull($peekableTokenStream->current());
    }
}
