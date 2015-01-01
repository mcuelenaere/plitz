<?php
namespace Plitz\Lexer;

class LexException extends \LogicException
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
}
