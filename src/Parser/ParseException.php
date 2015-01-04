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
     * @var int
     */
    private $templateColumn;

    /**
     * @param string $message
     * @param string $templateName
     * @param int $line
     * @param int $column
     */
    public function __construct($message, $templateName, $line, $column)
    {
        parent::__construct($message);

        $this->templateName = $templateName;
        $this->templateLine = $line;
        $this->templateColumn = $column;
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
     * @return int
     */
    public function getTemplateColumn()
    {
        return $this->templateColumn;
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
            $message = sprintf("Expected %s, but got %s instead at %s:%d:%d", $expectedTokens[0], $actualToken, $tokenStream->getSource(), $tokenStream->getLine(), $tokenStream->getColumn());
        } else if (count($expectedTokens) > 2) {
            $message = sprintf("Expected %s or %s, but got %s instead at %s:%d:%d", implode(', ', array_slice($expectedTokens, 0, count($expectedTokens) - 1)), $expectedTokens[count($expectedTokens) - 1], $actualToken, $tokenStream->getSource(), $tokenStream->getLine(), $tokenStream->getColumn());
        } else {
            $message = sprintf("Expected %s or %s, but got %s instead at %s:%d:%d", $expectedTokens[0], $expectedTokens[1], $actualToken, $tokenStream->getSource(), $tokenStream->getLine(), $tokenStream->getColumn());
        }

        return new ParseException($message, $tokenStream->getSource(), $tokenStream->getLine(), $tokenStream->getColumn());
    }
}
