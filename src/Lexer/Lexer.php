<?php
namespace Plitz\Lexer;

class Lexer
{
    private $stream;
    private $streamName;
    private $buffer;
    private $bufferSize;
    private $cursor;
    private $column;
    private $line;

    /**
     * @param resource $stream
     * @param string $streamName
     * @param int $bufferSize
     */
    public function __construct($stream, $streamName = 'n/a', $bufferSize = 1024)
    {
        assert($bufferSize > 2, "Buffer size should be at least 2 bytes");

        $this->stream = $stream;
        $this->streamName = $streamName;
        $this->bufferSize = $bufferSize;
        $this->cursor = 0;
        $this->column = 1;
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
     * @return int
     */
    public function getColumn()
    {
        return $this->column;
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
        $this->buffer = '';
        while (!feof($this->stream)) {
            $this->buffer .= fread($this->stream, $this->bufferSize);

            while(($blockPosition = strpos($this->buffer, '{{')) === false && !feof($this->stream)) {
                $this->buffer .= fread($this->stream, $this->bufferSize);
            }

            if ($blockPosition === false) {
                if (!empty($this->buffer)) {
                    yield [Tokens::T_RAW, $this->buffer];
                    $this->consume(strlen($this->buffer));
                }

                // we can only be in this case when reached EOF, but do another check just to be sure
                continue;
            }

            while ($blockPosition !== false) {
                // consume any RAW data before the block
                if ($blockPosition > 0) {
                    yield [Tokens::T_RAW, substr($this->buffer, 0, $blockPosition)];
                    $this->consume($blockPosition);
                }

                // consume the block prefix
                $this->consume(2);

                // fill the buffer with the whole contents of the block
                while(strpos($this->buffer, '}}') === false && !feof($this->stream)) {
                    $this->buffer .= fread($this->stream, $this->bufferSize);
                }

                // lex it
                foreach ($this->lexBlock() as $token) {
                    yield $token;
                }

                $blockPosition = strpos($this->buffer, '{{');
            }
        }

        // check if there's some remaining stuff in the buffer
        if (!empty($this->buffer)) {
            yield [Tokens::T_RAW, $this->buffer];
            $this->buffer = '';
        }
    }

    private function lexBlock()
    {
        static $tokenMapping = [
            '+'  => Tokens::T_PLUS,
            '-'  => Tokens::T_MINUS,
            '*'  => Tokens::T_MUL,
            '/'  => Tokens::T_DIV,
            '%'  => Tokens::T_MOD,

            '.'  => Tokens::T_ATTR_SEP,
            ','  => Tokens::T_COMMA,
            '('  => Tokens::T_OPEN_PAREN,
            ')'  => Tokens::T_CLOSE_PAREN,

            '==' => Tokens::T_EQ,
            '!=' => Tokens::T_NE,
            '>=' => Tokens::T_GE,
            '>'  => Tokens::T_GT,
            '<=' => Tokens::T_LE,
            '<'  => Tokens::T_LT,

            '&&' => Tokens::T_AND,
            '||' => Tokens::T_OR,

            '!'  => Tokens::T_NOT,
            '|'  => Tokens::T_PIPE,
        ];

        static $caseInsensitiveTokenMapping = [
            'BEGIN'   => Tokens::T_BLOCK_BEGIN,
            'END'     => Tokens::T_BLOCK_END,
            'IF'      => Tokens::T_BLOCK_IF,
            'ELSE IF' => Tokens::T_BLOCK_ELSE_IF,
            'ELSEIF'  => Tokens::T_BLOCK_ELSE_IF,
            'ELSE'    => Tokens::T_BLOCK_ELSE,
            'UNLESS'  => Tokens::T_BLOCK_UNLESS,

            'and'     => Tokens::T_AND,
            'or'      => Tokens::T_OR,
        ];

        while (true) {
            $this->skipWhitespace();

            $found = false;
            foreach ($caseInsensitiveTokenMapping as $string => $token) {
                if (self::equalsToCaseInsensitive($string)) {
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
            } else if (preg_match('|^(["\'])([^"\']*)\\1|', $this->buffer, $m)) {
                yield [Tokens::T_STRING, $m[2]];
                $this->consume(strlen($m[2]) + 2);
            } else if (self::equalsToCaseInsensitive('true')) {
                yield [Tokens::T_BOOL, true];
                $this->consume(4);
            } else if (self::equalsToCaseInsensitive('false')) {
                yield [Tokens::T_BOOL, false];
                $this->consume(5);
            } else if (preg_match('|^([A-z0-9$]+)|', $this->buffer, $m)) {
                yield [Tokens::T_LITERAL, $m[1]];
                $this->consume(strlen($m[1]));
            } else {
                if (empty($this->buffer)) {
                    $message = sprintf("Unexpected end of file at %s:%d:%d", $this->getStreamName(), $this->getLine(), $this->getColumn());
                } else {
                    // TODO: improve error message
                    $message = sprintf("Syntax error at %s:%d:%d", $this->getStreamName(), $this->getLine(), $this->getColumn());
                }
                throw new LexException($message, $this->getStreamName(), $this->getLine(), $this->getColumn());
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

    private function equalsToCaseInsensitive($text)
    {
        return preg_match('|^' . preg_quote($text, '|') . '[^a-z0-9]|i', $this->buffer);
    }

    private function startsWith($text)
    {
        return substr($this->buffer, 0, strlen($text)) === $text;
    }

    private function consume($bytes)
    {
        $numberOfNewlines = substr_count($this->buffer, "\n", 0, $bytes);
        if ($numberOfNewlines > 0) {
            $this->column = $bytes - strrpos(substr($this->buffer, 0, $bytes), "\n");
        } else {
            $this->column += $bytes;
        }
        $this->cursor += $bytes;
        $this->line += $numberOfNewlines;
        $this->buffer = substr($this->buffer, $bytes);
    }
}
