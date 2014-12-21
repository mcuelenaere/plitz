<?php
namespace Plitz\Parser;

use Plitz\Lexer\Tokens;
use Plitz\Lexer\TokenStream;

class Parser
{
    /**
     * @var PeekableTokenStream
     */
    private $tokenStream;

    /**
     * @var ExpressionParser
     */
    private $expressionParser;

    /**
     * @var Visitor
     */
    private $visitor;

    public function __construct(TokenStream $tokenStream, Visitor $visitor)
    {
        $this->tokenStream = new PeekableTokenStream($tokenStream);
        $this->expressionParser = new ExpressionParser($this->tokenStream);
        $this->visitor = $visitor;
    }

    public function parse()
    {
        $this->tokenStream->rewind();

        while ($this->tokenStream->valid()) {
            $this->parseStatement();
        }
    }

    private function parseStatement()
    {
        list($nextToken, ) = $this->tokenStream->peek();
        if ($nextToken === Tokens::T_LITERAL) {
            $expression = $this->expressionParser->parseExpression();
            $this->visitor->printBlock($expression);
        }

        list($token, $value) = $this->getNextToken();
        switch ($token) {
            case Tokens::T_RAW:
                $this->visitor->raw($value);
                break;
            case Tokens::T_BLOCK_BEGIN:
                $this->parseLoopBlock();
                break;
            case Tokens::T_BLOCK_IF:
            case Tokens::T_BLOCK_UNLESS:
                $this->parseIfBlock();
                break;
            default:
                throw ParseException::createInvalidTokenException([Tokens::T_RAW, Tokens::T_BLOCK_BEGIN, Tokens::T_BLOCK_IF, Tokens::T_BLOCK_UNLESS], $this->tokenStream);
        }
    }

    private function parseLoopBlock()
    {
        $loopCondition = $this->expressionParser->parseVariableOrMethodCallExpression();

        $this->visitor->loopBlock($loopCondition);

        // keep parsing statements until we see a block end
        list($nextToken, ) = $this->tokenStream->peek();
        while ($nextToken !== Tokens::T_BLOCK_END) {
            $this->parseStatement();

            list($nextToken, ) = $this->tokenStream->peek();
        }

        // consume T_BLOCK_END
        $this->consumeToken();

        // skip optional trailing literal
        if ($this->tokenStream->peek()[0] === Tokens::T_LITERAL) {
            $this->consumeToken();
        }

        $this->visitor->endLoopBlock();
    }

    private function parseIfBlock()
    {
        $invertedCondition = ($this->tokenStream->current()[0] === Tokens::T_BLOCK_UNLESS);

        $condition = $this->expressionParser->parseExpression();
        if ($invertedCondition) {
            $condition = new Expressions\Unary($condition, Expressions\Unary::OPERATION_NOT);
        }

        $this->visitor->ifBlock($condition);

        // keep parsing statements until we see a block end
        list($nextToken, ) = $this->tokenStream->peek();
        while ($nextToken !== Tokens::T_BLOCK_END) {
            // check if we have an "else" or "else if" block
            if ($nextToken === Tokens::T_BLOCK_ELSE) {
                $this->visitor->elseBlock();
                $this->consumeToken();
            } else if ($nextToken === Tokens::T_BLOCK_ELSE_IF) {
                $this->consumeToken();
                $condition = $this->expressionParser->parseExpression();
                $this->visitor->elseIfBlock($condition);
            }

            $this->parseStatement();

            list($nextToken, ) = $this->tokenStream->peek();
        }

        // consume T_BLOCK_END
        $this->consumeToken();

        // skip optional trailing literal
        if ($this->tokenStream->peek()[0] === Tokens::T_LITERAL) {
            $this->consumeToken();
        }

        $this->visitor->endIfBlock();
    }

    private function getNextToken()
    {
        $this->tokenStream->next();
        return $this->tokenStream->current();
    }

    private function consumeToken()
    {
        $this->tokenStream->next();
    }
}
