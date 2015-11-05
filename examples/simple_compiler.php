#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Plitz\Bindings\Blitz\JsCompiler;
use Plitz\Bindings\Blitz\PhpCompiler;
use Plitz\Compilers\BlitzCompiler;
use Plitz\Parser\Expressions;

$templatePath = getcwd();
$outputFormat = 'php';
foreach (getopt('hf:t:', ['help', 'format:', 'template-path:']) as $option => $value) {
    switch ($option) {
        case 'h':
        case 'help':
        default:
            printf("%s [options] <input file>\n\n", $argv[0]);
            printf("Options:\n");
            printf("\t-h, --help             Display this help message\n");
            printf("\t-f, --format           Controls the output format (one of: js, php, blitz)\n");
            printf("\t-t, --template-path    Specifies the path which is used when including other templates\n");
            exit(0);
        case 'f':
        case 'format':
            if (!in_array($value, ['js', 'php', 'blitz'])) {
                printf("Value '%s' is not one of: js, php, blitz\n", $value);
                exit(1);
            }

            $outputFormat = $value;
            break;
        case 't':
        case 'template-path':
            $templatePath = $value;
            break;
    }
}

if ($argc > 1 && !preg_match('|^--|', $argv[$argc - 1])) {
    $inputFilename = $argv[$argc - 1];
} else {
    $inputFilename = "php://stdin";
}

$lexer = new Plitz\Lexer\Lexer(fopen($inputFilename, "r"), $inputFilename);
$tokenStream = $lexer->lex();

switch ($outputFormat) {
    case 'php':
        $compiler = new PhpCompiler(STDOUT, $templatePath, new Plitz\Bindings\Blitz\Blitz('test.tpl', $templatePath));
        break;
    case 'js':
        $compiler = new JsCompiler(STDOUT, $templatePath);
        break;
    case 'blitz':
        $compiler = new BlitzCompiler(STDOUT);
        break;
}

$parser = new Plitz\Parser\Parser($tokenStream, $compiler);
$parser->parse();
