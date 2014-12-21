<?php
namespace Plitz\Lexer;

class Lexer
{
    private $stream;
    private $streamName;
    private $buffer;
    private $cursor;
    private $line;

    /**
     * @param resource $stream
     * @param string $streamName
     */
    public function __construct($stream, $streamName = 'n/a')
    {
        $this->stream = $stream;
        $this->streamName = $streamName;
        $this->cursor = 0;
        $this->line = 1;
    }

    /**
     * @return int
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * @return string
     */
    public function getStreamName()
    {
        return $this->streamName;
    }

    /**
     * @return LexerStream
     */
    public function lex()
    {
        return new LexerStream($this, $this->lexAll());
    }

    private function lexAll()
    {
        while (!feof($this->stream)) {
            $this->buffer = fread($this->stream, 1024);

            foreach ($this->lexBuffer() as $token) {
                yield $token;
            }
        }
    }

    private function lexBuffer()
    {
        while (!empty($this->buffer)) {
            if (strlen($this->buffer) < 2) {
                // TODO: fix this
                throw new \RuntimeException("Buffer is not large enough (" . strlen($this->buffer) . " bytes) to ensure correct lexing behaviour");
            }

            $blockPosition = strpos($this->buffer, '{{');
            if ($blockPosition === false) {
                if (strlen($this->buffer) > 0) {
                    yield [Tokens::T_RAW, $this->buffer];
                    $this->consume(strlen($this->buffer));
                }
                // no block found, break out of loop
                break;
            }

            // consume any RAW data before the block
            if ($blockPosition > 0) {
                yield [Tokens::T_RAW, substr($this->buffer, 0, $blockPosition)];
                $this->consume($blockPosition);
            }

            // consume the block prefix
            $this->consume(2);

            // lex it
            foreach ($this->lexBlock() as $token) {
                yield $token;
            }
        }
    }

    private function lexBlock()
    {
        static $tokenMapping = [
            '+'  => Tokens::T_PLUS,
            '-'  => Tokens::T_MINUS,
            '*'  => Tokens::T_MUL,
            '/'  => Tokens::T_DIV,

            '.'  => Tokens::T_ATTR_SEP,
            ','  => Tokens::T_COMMA,
            '('  => Tokens::T_OPEN_PAREN,
            ')'  => Tokens::T_CLOSE_PAREN,

            '!'  => Tokens::T_NOT,
            '==' => Tokens::T_EQ,
            '!=' => Tokens::T_NE,
            '>=' => Tokens::T_GE,
            '>'  => Tokens::T_GT,
            '<=' => Tokens::T_LE,
            '<'  => Tokens::T_LT,
        ];

        static $caseInsensitiveTokenMapping = [
            'BEGIN'   => Tokens::T_BLOCK_BEGIN,
            'END'     => Tokens::T_BLOCK_END,
            'IF'      => Tokens::T_BLOCK_IF,
            'ELSE'    => Tokens::T_BLOCK_ELSE,
            'ELSE IF' => Tokens::T_BLOCK_ELSE_IF,
            'ELSEIF'  => Tokens::T_BLOCK_ELSE_IF,
            'UNLESS'  => Tokens::T_BLOCK_UNLESS,
        ];

        while (true) {
            $this->skipWhitespace();

            $found = false;
            foreach ($caseInsensitiveTokenMapping as $string => $token) {
                if (self::startsWithCaseInsensitive($string)) {
                    yield [$token, null];
                    $this->consume(strlen($string));
                    $found = true;
                    break;
                }
            }

            if ($found) {
                continue;
            }

            foreach ($tokenMapping as $string => $token) {
                if (self::startsWith($string)) {
                    yield [$token, null];
                    $this->consume(strlen($string));
                    $found = true;
                    break;
                }
            }

            if ($found) {
                continue;
            }

            if (self::startsWith('}}')) {
                // consume the block suffix
                $this->consume(2);
                // end condition reached
                break;
            } else if (preg_match('|^([0-9\.]+)|', $this->buffer, $m)) {
                // TODO: validate whether this is a correct number
                $number = floatval($m[1]);
                yield [Tokens::T_NUMBER, $number];
                $this->consume(strlen($m[1]));
            } else if (preg_match('|^([A-z0-9]+)|', $this->buffer, $m)) {
                yield [Tokens::T_LITERAL, $m[1]];
                $this->consume(strlen($m[1]));
            } else if (preg_match('|^(["\'])([^"\']+)\\1|', $this->buffer, $m)) {
                yield [Tokens::T_STRING, $m[2]];
                $this->consume(strlen($m[2]) + 2);
            } else if (self::startsWithCaseInsensitive('true')) {
                yield [Tokens::T_BOOL, true];
                $this->consume(4);
            } else if (self::startsWithCaseInsensitive('false')) {
                yield [Tokens::T_BOOL, false];
                $this->consume(5);
            } else {
                throw new LexException("Syntax error", $this->getStreamName(), $this->getLine());
            }
        }
    }

    private function skipWhitespace()
    {
        $newPosition = strspn($this->buffer, " \t\n\r\0\x0B");
        if ($newPosition > 0) {
            $this->consume($newPosition);
        }
    }

    private function startsWithCaseInsensitive($text)
    {
        return strncasecmp($this->buffer, $text, strlen($text)) === 0;
    }

    private function startsWith($text)
    {
        return substr($this->buffer, 0, strlen($text)) === $text;
    }

    private function consume($bytes)
    {
        $this->cursor += $bytes;
        $this->line += substr_count($this->buffer, "\n", 0, $bytes);
        $this->buffer = substr($this->buffer, $bytes);
    }
}
