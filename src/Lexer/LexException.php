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
}
