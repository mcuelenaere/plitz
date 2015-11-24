<?php
namespace Plitz\Parser\Expressions;

use Plitz\Parser\Expression;

class ScopedInclude extends MethodCall
{
    /**
     * @var Expression[]
     */
    private $scope;

    /**
     * @param Expression $included
     * @param Expression[] $scope
     */
    public function __construct(Expression $included, array $scope)
    {
        parent::__construct('include', [$included]);

        $this->scope = $scope;
    }

    /**
     * @return Expression
     */
    public function getIncluded()
    {
        return $this->getArguments()[0];
    }

    /**
     * @return Expression[]
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * @return string
     */
    public function toString()
    {
        $str = 'include(' . $this->getArguments()[0];
        if (count($this->scope) > 0) {
            $str .= ', ';
            $first = true;
            foreach ($this->scope as $key => $value) {
                if (!$first) {
                    $str .= ', ';
                }
                $str .= $key . '=' . strval($value);
                $first = false;
            }
        }
        $str .= ')';
        return $str;
    }
}
