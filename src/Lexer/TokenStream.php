<?php
namespace Plitz\Lexer;

interface TokenStream extends \Traversable
{
    /**
     * @return string
     */
    public function getSource();

    /**
     * @return int
     */
    public function getLine();
}
