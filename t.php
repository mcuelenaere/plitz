<?php
require_once __DIR__ . '/vendor/autoload.php';

use Plitz\Bindings\Blitz\JsCompiler;
use Plitz\Bindings\Blitz\PhpCompiler;
use Plitz\Parser\Expressions;

if (!empty($argv[1])) {
    $lexer = new Plitz\Lexer\Lexer(fopen($argv[2], "r"), $argv[2]);
    $basePath = $argv[1];
} else {
    $text = <<<EOF
Hi {{ name }}, how are you ?
{{ BEGIN friends }}
    * {{ name }}
      {{ BEGIN friends }}
        * {{ _top.name }} (or {{ _parent._parent.name }}) is friended with {{ _parent.name }}, which is friended with {{ name }}
      {{ END }}
      {{ dump(_)|raw }}
      {{ assignVar('foo', _parent.bar) }}
      {{ assignVar('this', _) }}
{{ END }}

{{ IF person.name == "boel" }}
    YES
{{ END }}

{{ UNLESS isPresent }}
    is er
{{ ELSE }}
    is er niet
{{ END isPresent }}

{{ methode("abc", def, 4, 18 * 22 + 16, strtoupper("blaat")) }}

{{ 1 + 2 }}
{{ 3 * -4 }}
{{ 5 * 6 + 7 / 8 }}
{{ (9 + 10) * 8 >= 11 }}
{{ ---3 != +3 }}
{{ test(5 + 5, "def") != var }}

EOF;

    $s = fopen('php://memory', 'r+');
    fwrite($s, $text);
    rewind($s);

    $lexer = new Plitz\Lexer\Lexer($s, 'test.tpl');
    $basePath = null;
}

$tokenStream = $lexer->lex();

if (false) {
    $compiler = new PhpCompiler(STDOUT, $basePath);
} else {
    $compiler = new JsCompiler(STDOUT, $basePath);
}

$parser = new Plitz\Parser\Parser($tokenStream, $compiler);
$parser->parse();
