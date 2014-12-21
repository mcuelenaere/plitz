<?php
namespace Plitz\Parser;

abstract class Expression
{
    /**
     * @return string
     */
    abstract public function toString();

    public function __toString()
    {
        return $this->toString();
    }
}
