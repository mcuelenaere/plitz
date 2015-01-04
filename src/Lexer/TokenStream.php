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

    /**
     * @return int
     */
    public function getColumn();
}
