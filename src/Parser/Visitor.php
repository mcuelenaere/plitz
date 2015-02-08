<?php
namespace Plitz\Parser;

interface Visitor
{
    public function startOfStream();
    public function endOfStream();

    public function raw($data);

    public function ifBlock(Expression $condition);
    public function elseBlock();
    public function elseIfBlock(Expression $condition);
    public function endIfBlock();

    public function loopBlock(Expression $variable);
    public function endLoopBlock();

    public function printBlock(Expression $value);

    public function comment($data);
}
