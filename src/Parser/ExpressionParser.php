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
        return $this->parseExpressionWithPrecedence($this->parsePrimaryExpression(), 0);
    }

    private function parseExpressionWithPrecedence($left, $precedence)
    {
        list($token, ) = $this->tokenStream->current();
        while (isset(self::$binaryTokens[$token]) && self::$precedences[$token] >= $precedence) {
            $operatorToken = $token;
            $operator = self::$binaryTokens[$token];
            $this->tokenStream->next();

            $right = $this->parsePrimaryExpression();

            list($token, ) = $this->tokenStream->current();
            while (
                isset(self::$binaryTokens[$token]) &&
                (
                    self::$precedences[$token] > self::$precedences[$operatorToken] ||
                    (self::$associativity[$token] === self::RIGHT_ASSOCIATIVE && self::$precedences[$token] === self::$precedences[$operatorToken])
                )
            ) {
                $right = $this->parseExpressionWithPrecedence($right, self::$precedences[$token]);

                list($token, ) = $this->tokenStream->current();
            }

            $left = new Expressions\Binary($left, $right, $operator);
        }

        return $left;
    }

    private function parsePrimaryExpression()
    {
        list($token, $value) = $this->tokenStream->current();
        switch ($token) {
            case Tokens::T_LITERAL:
                return $this->parseVariableOrMethodCallExpression();
            case Tokens::T_NOT:
                $this->tokenStream->next();
                return new Expressions\Unary($this->parsePrimaryExpression(), Expressions\Unary::OPERATION_NOT);
            case Tokens::T_MINUS:
                $this->tokenStream->next();
                return new Expressions\Unary($this->parsePrimaryExpression(), Expressions\Unary::OPERATION_NEGATE);
            case Tokens::T_PLUS:
                $this->tokenStream->next();
                return $this->parsePrimaryExpression();
            case Tokens::T_BOOL:
            case Tokens::T_STRING:
            case Tokens::T_NUMBER:
                $this->tokenStream->next();
                return new Expressions\Scalar($value);
            case Tokens::T_OPEN_PAREN:
                // eat T_OPEN_PAREN
                $this->tokenStream->next();

                // parse expression within parens
                $expression = $this->parseExpression();

                list($token, ) = $this->tokenStream->current();
                if ($token !== Tokens::T_CLOSE_PAREN) {
                    throw ParseException::createInvalidTokenException([Tokens::T_CLOSE_PAREN], $this->tokenStream);
                }

                // eat T_CLOSE_PAREN
                $this->tokenStream->next();

                return $expression;
            default:
                throw ParseException::createInvalidTokenException([Tokens::T_BOOL, Tokens::T_STRING, Tokens::T_NUMBER, Tokens::T_MINUS, Tokens::T_PLUS, Tokens::T_NOT, Tokens::T_OPEN_PAREN, Tokens::T_LITERAL], $this->tokenStream);
        }
    }

    /**
     * @return Expression
     */
    public function parseVariableOrMethodCallExpression()
    {
        list($token, $name) = $this->tokenStream->current();
        if ($token !== Tokens::T_LITERAL) {
            throw ParseException::createInvalidTokenException([Tokens::T_LITERAL], $this->tokenStream);
        }
        $this->tokenStream->next();

        list($token, ) = $this->tokenStream->current();

        // check for method call
        if ($token === Tokens::T_OPEN_PAREN) {
            // eat open paren
            $this->tokenStream->next();

            $arguments = [];
            do {
                list($token, ) = $this->tokenStream->current();

                if ($token !== Tokens::T_CLOSE_PAREN) {
                    $arguments[] = $this->parseExpression();

                    list($token, ) = $this->tokenStream->current();
                    if ($token !== Tokens::T_COMMA && $token !== Tokens::T_CLOSE_PAREN) {
                        throw ParseException::createInvalidTokenException([Tokens::T_COMMA, Tokens::T_CLOSE_PAREN], $this->tokenStream);
                    }

                    // eat T_COMMA or T_CLOSE_PAREN
                    $this->tokenStream->next();
                }
            } while ($token !== Tokens::T_CLOSE_PAREN);

            return new Expressions\MethodCall($name, $arguments);
        }

        $expression = new Expressions\Variable($name);
        while ($token === Tokens::T_ATTR_SEP) {
            $this->tokenStream->next();

            list($token, $attributeName) = $this->tokenStream->current();
            if ($token !== Tokens::T_LITERAL) {
                throw ParseException::createInvalidTokenException([Tokens::T_LITERAL], $this->tokenStream);
            }
            $this->tokenStream->next();

            $expression = new Expressions\GetAttribute($expression, $attributeName);

            list($token, ) = $this->tokenStream->current();
        }

        return $expression;
    }
}
