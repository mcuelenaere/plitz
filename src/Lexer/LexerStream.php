<?php
namespace Plitz\Lexer;

class LexerStream implements \IteratorAggregate, TokenStream
{
    private $lexer;
    private $generator;

    public function __construct(Lexer $lexer, \Generator $generator)
    {
        $this->lexer = $lexer;
        $this->generator = $generator;
    }

    /**
     * @inheritdoc
     */
    public function getIterator()
    {
        return $this->generator;
    }

    /**
     * @inheritdoc
     */
    public function getSource()
    {
        return $this->lexer->getStreamName();
    }

    /**
     * @inheritdoc
     */
    public function getLine()
    {
        return $this->lexer->getLine();
    }

    /**
     * @inheritdoc
     */
    public function getColumn()
    {
        return $this->lexer->getColumn();
    }
}
