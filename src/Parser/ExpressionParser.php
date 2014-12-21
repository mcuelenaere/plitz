<?php
namespace Plitz\Parser;

use Plitz\Lexer\Tokens;


/**
 * This parser uses the "Precedence climbing" algorithm.
 *
 * @see https://en.wikipedia.org/wiki/Operator-precedence_parser
 */
class ExpressionParser
{
    private static $precedences = [
        Tokens::T_EQ    => 0,
        Tokens::T_NE    => 0,
        Tokens::T_GT    => 0,
        Tokens::T_GE    => 0,
        Tokens::T_LT    => 0,
        Tokens::T_LE    => 0,

        Tokens::T_PLUS  => 1,
        Tokens::T_MINUS => 1,

        Tokens::T_MUL   => 2,
        Tokens::T_DIV   => 2,
    ];

    private static $binaryTokens = [
        Tokens::T_EQ    => Expressions\Binary::OPERATOR_EQUALS,
        Tokens::T_NE    => Expressions\Binary::OPERATOR_NOT_EQUALS,
        Tokens::T_GT    => Expressions\Binary::OPERATOR_GREATER_THAN,
        Tokens::T_GE    => Expressions\Binary::OPERATOR_GREATER_THAN_OR_EQUALS,
        Tokens::T_LT    => Expressions\Binary::OPERATOR_LESS_THAN,
        Tokens::T_LE    => Expressions\Binary::OPERATOR_LESS_THAN_OR_EQUALS,

        Tokens::T_PLUS  => Expressions\Binary::OPERATOR_ADD,
        Tokens::T_MINUS => Expressions\Binary::OPERATOR_SUBTRACT,
        Tokens::T_MUL   => Expressions\Binary::OPERATOR_MULTIPLY,
        Tokens::T_DIV   => Expressions\Binary::OPERATOR_DIVIDE,
    ];

    const LEFT_ASSOCIATIVE  = 0;
    const RIGHT_ASSOCIATIVE = 1;

    private static $associativity = [
        Tokens::T_EQ    => self::RIGHT_ASSOCIATIVE,
        Tokens::T_NE    => self::RIGHT_ASSOCIATIVE,
        Tokens::T_GT    => self::RIGHT_ASSOCIATIVE,
        Tokens::T_GE    => self::RIGHT_ASSOCIATIVE,
        Tokens::T_LT    => self::RIGHT_ASSOCIATIVE,
        Tokens::T_LE    => self::RIGHT_ASSOCIATIVE,

        Tokens::T_PLUS  => self::LEFT_ASSOCIATIVE,
        Tokens::T_MINUS => self::LEFT_ASSOCIATIVE,
        Tokens::T_MUL   => self::LEFT_ASSOCIATIVE,
        Tokens::T_DIV   => self::LEFT_ASSOCIATIVE,
    ];

    /**
     * @var PeekableTokenStream
     */
    private $tokenStream;

    public function __construct(PeekableTokenStream $tokenStream)
    {
        $this->tokenStream = $tokenStream;
    }

    /**
     * @return Expression
     */
    public function parseExpression()
    {
        return $this->parseExpressionWithPrecedence();
    }

    private function parseExpressionWithPrecedence($precedence = 0)
    {
        $left = $this->parsePrimaryExpression();

        list($token, ) = $this->tokenStream->peek();
        while (isset(self::$binaryTokens[$token]) && self::$precedences[$token] >= $precedence) {
            $this->consumeToken();

            $operator = self::$binaryTokens[$token];
            $right = $this->parseExpression($precedence + self::$associativity[$token]);
            $left = new Expressions\Binary($left, $right, $operator);

            list($token, ) = $this->tokenStream->peek();
        }

        return $left;
    }

    private function parsePrimaryExpression()
    {
        list($token, ) = $this->tokenStream->peek();
        if ($token === Tokens::T_LITERAL) {
            return $this->parseVariableOrMethodCallExpression();
        }

        list($token, $value) = $this->getNextToken();
        switch ($token) {
            case Tokens::T_NOT:
                return new Expressions\Unary($this->parseExpression(), Expressions\Unary::OPERATION_NOT);
            case Tokens::T_MINUS:
                return new Expressions\Unary($this->parseExpression(), Expressions\Unary::OPERATION_NEGATE);
            case Tokens::T_PLUS:
                return $this->parseExpression();
            case Tokens::T_BOOL:
            case Tokens::T_STRING:
            case Tokens::T_NUMBER:
                return new Expressions\Scalar($value);
            default:
                throw ParseException::createInvalidTokenException([Tokens::T_BOOL, Tokens::T_STRING, Tokens::T_NUMBER, Tokens::T_MINUS, Tokens::T_PLUS, Tokens::T_NOT], $this->tokenStream);
        }
    }

    /**
     * @return Expression
     */
    public function parseVariableOrMethodCallExpression()
    {
        list($token, $name) = $this->getNextToken();
        if ($token !== Tokens::T_LITERAL) {
            throw ParseException::createInvalidTokenException([Tokens::T_LITERAL], $this->tokenStream);
        }

        list($nextToken, ) = $this->tokenStream->peek();

        // check for method call
        if ($nextToken === Tokens::T_OPEN_PAREN) {
            // eat open paren
            $this->consumeToken();

            $arguments = [];
            do {
                list($nextToken, ) = $this->tokenStream->peek();

                if ($nextToken !== Tokens::T_CLOSE_PAREN) {
                    $arguments[] = $this->parseExpression();

                    list($token, ) = $this->getNextToken();
                    if ($token !== Tokens::T_COMMA && $token !== Tokens::T_CLOSE_PAREN) {
                        throw ParseException::createInvalidTokenException([Tokens::T_COMMA, Tokens::T_CLOSE_PAREN], $this->tokenStream);
                    }

                    $nextToken = $token;
                }
            } while ($nextToken !== Tokens::T_CLOSE_PAREN);

            // eat T_CLOSE_PAREN
            $this->consumeToken();

            return new Expressions\MethodCall($name, $arguments);
        }

        $expression = new Expressions\Variable($name);
        while ($nextToken === Tokens::T_ATTR_SEP) {
            $this->consumeToken();

            list($token, $attributeName) = $this->getNextToken();
            if ($token !== Tokens::T_LITERAL) {
                throw ParseException::createInvalidTokenException([Tokens::T_LITERAL], $this->tokenStream);
            }

            $expression = new Expressions\GetAttribute($expression, $attributeName);

            list($nextToken, ) = $this->tokenStream->peek();
        }

        return $expression;
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
