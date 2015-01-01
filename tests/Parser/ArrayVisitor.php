<?php
namespace Plitz\Tests\Parser;

use Plitz\Parser\Expression;
use Plitz\Parser\Visitor;

class ArrayVisitor implements Visitor
{
    /**
     * @var array
     */
    private $calls = [];

    /**
     * @return array
     */
    public function getCalls()
    {
        return $this->calls;
    }

    public function raw($data)
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'arguments' => func_get_args()
        ];
    }

    public function ifBlock(Expression $condition)
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'arguments' => func_get_args()
        ];
    }

    public function elseBlock()
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'arguments' => func_get_args()
        ];
    }

    public function elseIfBlock(Expression $condition)
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'arguments' => func_get_args()
        ];
    }

    public function endIfBlock()
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'arguments' => func_get_args()
        ];
    }

    public function loopBlock(Expression $variable)
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'arguments' => func_get_args()
        ];
    }

    public function endLoopBlock()
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'arguments' => func_get_args()
        ];
    }

    public function printBlock(Expression $value)
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'arguments' => func_get_args()
        ];
    }
}
