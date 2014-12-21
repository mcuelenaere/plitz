<?php
namespace Plitz\Parser;

class ParseException extends \LogicException
{
    /**
     * @var string
     */
    private $templateName;

    /**
     * @var int
     */
    private $templateLine;

    /**
     * @param string $message
     * @param string $templateName
     * @param int $line
     */
    public function __construct($message, $templateName, $line)
    {
        parent::__construct($message);

        $this->templateName = $templateName;
        $this->templateLine = $line;
    }

    /**
     * @return string
     */
    public function getTemplateName()
    {
        return $this->templateName;
    }

    /**
     * @return int
     */
    public function getTemplateLine()
    {
        return $this->templateLine;
    }

    /**
     * @param array $expectedTokens
     * @param PeekableTokenStream $tokenStream
     * @return ParseException
     */
    public static function createInvalidTokenException(array $expectedTokens, PeekableTokenStream $tokenStream)
    {
        list($actualToken, ) = $tokenStream->current();

        if (count($expectedTokens) === 1) {
            return new ParseException("Expected {$expectedTokens[0]}, but got {$actualToken} instead", $tokenStream->getSource(), $tokenStream->getLine());
        } else if (count($expectedTokens) > 2) {
            return new ParseException("Expected " . implode(', ', array_slice($expectedTokens, 0, count($expectedTokens) - 1)) . " or " . $expectedTokens[count($expectedTokens) - 1] . ", but got {$actualToken} instead", $tokenStream->getSource(), $tokenStream->getLine());
        } else {
            return new ParseException("Expected {$expectedTokens[0]} or {$expectedTokens[1]}, but got {$actualToken} instead", $tokenStream->getSource(), $tokenStream->getLine());
        }
    }
}
