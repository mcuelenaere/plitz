<?php
namespace Plitz\Bindings\Blitz;

use Plitz\Lexer\Lexer;
use Plitz\Lexer\LexException;
use Plitz\Parser\ParseException;
use Plitz\Parser\Parser;

/**
 * Compatibility layer for Blitz.
 *
 * This class tries to be compatible with the Blitz implementation, but its behaviour might deviate slightly.
 * It is a thin layer around the whole Plitz infrastructure.
 *
 * @see http://alexeyrybak.com/blitz/blitz_en.html
 */
class Blitz
{
    /**
     * Template root
     * @var string
     */
    private $templateRoot;

    /**
     * Template filename
     *
     * @var string
     */
    private $templatePath;

    /**
     * Compiled template filename
     *
     * @var string
     */
    private $compiledTemplatePath;

    /**
     * Global variables
     *
     * @var array
     */
    private $globalVariables;

    /**
     *
     * @var array
     */
    private $iterations;

    public function __construct($filename, $templateRoot = null)
    {
        /*
         * INI settings:
         *
         * blitz.throw_exceptions
         * blitz.var_prefix
         * blitz.tag_open
         * blitz.tag_close
         * blitz.tag_open_alt
         * blitz.tag_close_alt
         * blitz.comment_open
         * blitz.comment_close
         * blitz.enable_alternative_tags
         * blitz.enable_comments
         * blitz.path
         * blitz.remove_spaces_around_context_tags
         * blitz.warn_context_duplicates
         * blitz.check_recursion
         * blitz.scope_lookup_limit
         * blitz.lower_case_method_names
         * blitz.enable_include
         * blitz.enable_callbacks
         * blitz.enable_php_callbacks
         * blitz.php_callbacks_first
         * blitz.auto_escape
         */

        // TODO: conditionally support blitz.auto_escape

        if ($templateRoot === null) {
            $templateRoot = ini_get('blitz.path');
        }

        $this->globalVariables = [];
        $this->iterations = [];
        $this->templateRoot = rtrim($templateRoot, DIRECTORY_SEPARATOR);
        $this->templatePath = $this->templateRoot . DIRECTORY_SEPARATOR . $filename;
        $this->compiledTemplatePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'plitz' . DIRECTORY_SEPARATOR . $filename . '.php';
    }

    private function parseAndCompile($inputStream, $filename)
    {
        $outputStream = fopen($this->compiledTemplatePath, "w");
        try {
            $lexer = new Lexer($inputStream, $filename);
            $compiler = new PhpCompiler($outputStream, $this->templateRoot, $this);
            $parser = new Parser($lexer->lex(), $compiler);
            $parser->parse();
        } catch (LexException $ex) {
            $msg = sprintf("blitz::blitz(): SYNTAX ERROR: %s (%s: line %d, pos %d)", $ex->getMessage(), $ex->getTemplateName(), $ex->getTemplateLine(), $ex->getTemplateColumn());
            throw new \Exception($msg, 0, $ex);
        } catch (ParseException $ex) {
            $msg = sprintf("blitz::blitz(): SYNTAX ERROR: %s (%s: line %d, pos %d)", $ex->getMessage(), $ex->getTemplateName(), $ex->getTemplateLine(), $ex->getTemplateColumn());
            throw new \Exception($msg, 0, $ex);
        } finally {
            fclose($outputStream);
        }
    }

    /**
     * Set context and iterate it
     *
     * @param  string $context_path
     * @param  array  $parameters
     * @return bool
     */
    public function block($context_path, $parameters = array())
    {
        throw new \LogicException("Not implemented");
    }

    /**
     * Clean up context variables and iterations
     *
     * @param  string $context_path
     * @param  bool $warn_notfound
     * @return bool
     */
    public function clean($context_path='/', $warn_notfound = true)
    {
        throw new \LogicException("Not implemented");
    }

    /**
     * Set current context
     *
     * @param  string $context_path
     * @return bool
     */
    public function context($context_path)
    {
        throw new \LogicException("Not implemented");
    }

    /**
     * Dump template structure
     *
     * @return bool
     */
    public function dumpStruct()
    {
        throw new \LogicException("Not implemented");
    }

    /**
     * Check if context exists
     *
     * @param  string $context_path
     * @return bool
     */
    public function hasContext($context_path)
    {
        throw new \LogicException("Not implemented");
    }

    /**
     * Include another template (method Blitz::include()).
     *
     * Using underscore 'cause include is reserved php word
     *
     * @param  string $filename
     * @param  array  $params
     * @return string
     */
    public function _include($filename, array $params=[])
    {
        throw new \LogicException("Not implemented");
    }

    /**
     * Iterate context
     *
     * @param  string $context_path
     * @return bool
     */
    public function iterate($context_path = '/')
    {
        throw new \LogicException("Not implemented");
    }

    /**
     * Load template body from PHP string variable
     *
     * @param  string $body
     * @return bool
     */
    public function load($body)
    {
        $stream = fopen("php://temp", "r+");
        fwrite($stream, $body);
        fseek($stream, 0, SEEK_SET);
        try {
            $this->parseAndCompile($stream, 'n/a');
        } finally {
            fclose($stream);
        }

        return true;
    }

    /**
     * Helper function which includes a PHP file with a clean scope
     * @param array $context
     * @param self $blitz
     * @param string $templatePath
     */
    private static function renderTemplate(array $context, Blitz $blitz/*, $templatePath*/)
    {
        include(func_get_arg(2));
    }

    /**
     * Build and return the result
     *
     * @param  array $iterations
     * @return string
     */
    public function parse(array $iterations = [])
    {
        ob_start();
        try {
            $this->display($iterations);
            $result = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        return $result;
    }

    /**
     * Build and display the result
     *
     * @param  array $iterations
     */
    public function display(array $iterations = [])
    {
        // TODO: disable this check in production
        if (!is_file($this->compiledTemplatePath) || filemtime($this->templatePath) >= filemtime($this->compiledTemplatePath)) {
            if (!is_dir(dirname($this->compiledTemplatePath))) {
                // recursively create parent directories
                mkdir(dirname($this->compiledTemplatePath), 0755, true);
            }

            // write compiled template
            $input = fopen($this->templatePath, "r");
            try {
                $this->parseAndCompile($input, $this->templatePath);
            } finally {
                fclose($input);
            }
        }

        $this->iterations[] = $iterations;
        $currentErrorLevel = error_reporting();
        try {
            // TODO: remove this E_NOTICE hack
            error_reporting($currentErrorLevel &~ E_NOTICE);
            self::renderTemplate(array_merge($this->globalVariables, $iterations), $this, $this->compiledTemplatePath);
        } finally {
            error_reporting($currentErrorLevel);
            array_pop($this->iterations);
        }
    }

    /**
     * Set variables or iterations
     *
     * @param  array $parameters
     * @return bool
     */
    public function set($parameters)
    {
        throw new \LogicException("Not implemented");
    }

    /**
     * Set global variables
     *
     * @param  array $parameters
     * @return bool
     */
    public function setGlobal(array $parameters)
    {
        $this->globalVariables = array_merge($this->globalVariables, $parameters);
    }

    /**
     * Get global variables
     * @return array
     */
    public function getGlobals()
    {
        return $this->globalVariables;
    }

    /**
     * Get global variables
     */
    public function cleanGlobals()
    {
        $this->globalVariables = [];
    }

    /**
     * Returns template path list.
     *
     * @return array
     */
    public function getStructure()
    {
        throw new \LogicException("Not implemented");
    }

    /**
     * Returns iterations.
     *
     * @return array
     */
    public function getIterations()
    {
        return $this->iterations;
    }

    /**
     * Returns tokenized template structure.
     * Just like dumpStruct but returns structure array.
     *
     * @return array
     */
    public function getTokens()
    {
        throw new \LogicException("Not implemented");
    }

    public function __call($name, $arguments)
    {
        static $aliases = [
            '_include' => 'include',
            'dump_struct' => 'dumpStruct',
            'get_struct' => 'getStruct',
            'get_iterations' => 'getIterations',
            'get_context' => 'getContext',
            'has_context' => 'hasContext',
            'set_global' => 'setGlobal',
            'set_globals' => 'setGlobal',
            'get_globals' => 'getGlobals',
            'clean_globals' => 'cleanGlobals',
            'assign' => 'set',
            'get_error' => 'getError',
        ];

        if (isset($aliases[$name])) {
            return call_user_func_array([$this, $aliases[$name]], $arguments);
        } else {
            throw new \BadMethodCallException('Method ' . $name . 'doesn\'t exist');
        }
    }
}
