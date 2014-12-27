<?php
namespace Plitz\Tests\Lexer;

use Plitz\Lexer\TokenStream;

// this is necessary because Mockery implements \IteratorAggregate by default, which makes mocking harder
interface MockableTokenstream extends TokenStream, \Iterator
{
}
