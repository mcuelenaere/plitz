<?php
namespace Plitz\Parser;

use Plitz\Lexer\TokenStream;

class PeekableTokenStream implements \Iterator, TokenStream
{
    /**
     * @var TokenStream
     */
    private $tokenStream;

    /**
     * @var \Iterator
     */
    private $iterator;

    private $previousKey;
    private $previousToken;
    private $previousValid;

    public function __construct(TokenStream $tokenStream)
    {
        $this->tokenStream = $tokenStream;
        $this->iterator = new \IteratorIterator($tokenStream);
    }

    /**
     * @inheritdoc
     */
    public function current()
    {
        return $this->previousToken;
    }

    /**
     * @inheritdoc
     */
    public function key()
    {
        return $this->previousKey;
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        $this->iterator->rewind();

        if ($this->iterator->valid()) {
            $this->previousKey = $this->iterator->key();
            $this->previousToken = $this->iterator->current();
            $this->previousValid = $this->iterator->valid();

            // iterate to second element
            $this->iterator->next();
        }
    }

    /**
     * @inheritdoc
     */
    public function next()
    {
        if ($this->iterator->valid()) {
            $this->previousKey = $this->iterator->key();
            $this->previousToken = $this->iterator->current();
            $this->previousValid = $this->iterator->valid();

            // iterate to next element
            $this->iterator->next();
        } else {
            $this->previousValid = false;
            $this->previousKey = null;
            $this->previousToken = null;
        }
    }

    /**
     * @inheritdoc
     */
    public function valid()
    {
        return $this->previousValid;
    }

    /**
     * @return mixed The value of the next element
     */
    public function peek()
    {
        return $this->iterator->current();
    }

    /**
     * @inheritdoc
     */
    public function getSource()
    {
        return $this->tokenStream->getSource();
    }

    /**
     * @inheritdoc
     */
    public function getLine()
    {
        return $this->tokenStream->getLine();
    }
}
