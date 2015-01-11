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
        $this->visitor->startOfStream();

        $this->tokenStream->rewind();

        while ($this->tokenStream->valid()) {
            $this->parseStatement();
        }

        $this->visitor->endOfStream();
    }

    private function parseStatement()
    {
        list($token, $value) = $this->tokenStream->current();
        switch ($token) {
            case Tokens::T_RAW:
                $this->visitor->raw($value);
                $this->tokenStream->next();
                break;
            case Tokens::T_BLOCK_BEGIN:
                $this->parseLoopBlock();
                break;
            case Tokens::T_BLOCK_IF:
            case Tokens::T_BLOCK_UNLESS:
                $this->parseIfBlock();
                break;
            default:
                // TODO: this is a bit hacky, we assume everything else is a print block!
                $expression = $this->expressionParser->parseExpression();
                $this->visitor->printBlock($expression);
                break;
        }
    }

    private function parseLoopBlock()
    {
        list($token, ) = $this->tokenStream->current();
        if ($token !== Tokens::T_BLOCK_BEGIN) {
            throw ParseException::createInvalidTokenException([Tokens::T_BLOCK_BEGIN], $this->tokenStream);
        }
        $this->tokenStream->next();

        $loopCondition = $this->expressionParser->parseVariableOrMethodCallExpression();

        $this->visitor->loopBlock($loopCondition);

        // keep parsing statements until we see a block end
        list($token, ) = $this->tokenStream->current();
        while ($token !== Tokens::T_BLOCK_END) {
            $this->parseStatement();

            list($token, ) = $this->tokenStream->current();
        }

        // consume T_BLOCK_END
        $this->tokenStream->next();

        // skip optional trailing literal
        if ($this->tokenStream->current()[0] === Tokens::T_LITERAL) {
            $this->tokenStream->next();
        }

        $this->visitor->endLoopBlock();
    }

    private function parseIfBlock()
    {
        list($token, ) = $this->tokenStream->current();
        if ($token !== Tokens::T_BLOCK_IF && $token !== Tokens::T_BLOCK_UNLESS) {
            throw ParseException::createInvalidTokenException([Tokens::T_BLOCK_IF, Tokens::T_BLOCK_UNLESS], $this->tokenStream);
        }
        $invertedCondition = ($token === Tokens::T_BLOCK_UNLESS);
        $this->tokenStream->next();

        $condition = $this->expressionParser->parseExpression();
        if ($invertedCondition) {
            $condition = new Expressions\Unary($condition, Expressions\Unary::OPERATION_NOT);
        }

        $this->visitor->ifBlock($condition);

        // keep parsing statements until we see a block end
        list($token, ) = $this->tokenStream->current();
        while ($token !== Tokens::T_BLOCK_END) {
            // check if we have an "else" or "else if" block
            if ($token === Tokens::T_BLOCK_ELSE) {
                $this->visitor->elseBlock();
                $this->tokenStream->next();
            } else if ($token === Tokens::T_BLOCK_ELSE_IF) {
                $this->tokenStream->next();
                $condition = $this->expressionParser->parseExpression();
                $this->visitor->elseIfBlock($condition);
            }

            $this->parseStatement();

            list($token, ) = $this->tokenStream->current();
        }

        // consume T_BLOCK_END
        $this->tokenStream->next();

        // skip optional trailing literal
        if ($this->tokenStream->current()[0] === Tokens::T_LITERAL) {
            $this->tokenStream->next();
        }

        $this->visitor->endIfBlock();
    }
}
