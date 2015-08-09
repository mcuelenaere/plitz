## Plitz

[![travis ci](https://travis-ci.org/mcuelenaere/plitz.svg?branch=master)](https://travis-ci.org/mcuelenaere/plitz)
[![coveralls](https://coveralls.io/repos/mcuelenaere/plitz/badge.svg?branch=master)](https://coveralls.io/github/mcuelenaere/plitz)
[![github tag](https://img.shields.io/github/tag/mcuelenaere/plitz.svg)](https://github.com/mcuelenaere/plitz/tags)
[![packagist downloads](https://img.shields.io/packagist/dt/mcuelenaere/plitz.svg)](https://packagist.org/packages/mcuelenaere/plitz)

Plitz is a pure PHP port of the [Blitz PHP template extension](https://github.com/alexeyrybak/blitz).

Installation
------------

Install with composer:

```bash
  composer require mcuelenaere/plitz
```

Usage
-----

There are 2 ways to use the functionality provided by Plitz:

#### Blitz compatibility layer

```php
$template = <<<EOF
  Hello {{ audience }}!
EOF
;

$assignments = [
  'audience' => 'world'
];

// construct Blitz object
$blitz = new Plitz\Bindings\Blitz\Blitz();

// load template
$blitz->load($template);

// render template to stdout
$blitz->display($assignments);
```

#### Direct access to Plitz classes
```php
$template = <<<EOF
  Hello {{ audience }}!
EOF
;

$assignments = [
  'audience' => 'world'
];

// wrap template in a simple data:// stream
$inputStream = fopen("data://text/plain;base64," . base64_encode($template), "r");
// write compiled template to a memory buffer
$outputStream = fopen("php://memory", "r+");

try {
  // setup the required infrastructure
  $lexer = new Plitz\Lexer\Lexer($inputStream, "no template filename available");
  $compiler = new Plitz\Compilers\PhpCompiler($outputStream); // or perhaps you want the Plitz\Compilers\JsCompiler ?
  $parser = new Plitz\Parser\Parser($lexer->lex(), $compiler);
  
  // lex and parse from the input stream and compile to the output stream
  $parser->parse();
  
  // retrieve the compiled code from the memory stream
  fseek($outputStream, 0, SEEK_SET);
  $compiledCode = stream_get_contents($outputStream);
} catch (Plitz\Lexer\LexException $ex) {
  printf("We got an exception from the lexer: %s (%s: line %d, pos %d)", $ex->getMessage(), $ex->getTemplateName(), $ex->getTemplateLine(), $ex->getTemplateColumn());
  exit(1);
} catch (Plitz\Parser\ParseException $ex) {
  printf("We got an exception from the parser: %s (%s: line %d, pos %d)", $ex->getMessage(), $ex->getTemplateName(), $ex->getTemplateLine(), $ex->getTemplateColumn());
  exit(1);
} finally {
  // cleanup when we're done
  fclose($inputStream);
  fclose($outputStream);
}

// create a function from the compiled code
$templateFunction = create_function('$context', 'ob_start(); ?>' . $compiledCode . '<?php return ob_get_clean();');

// and last but not least: actually run it!
echo $templateFunction($assignments);
```

Design
------

Plitz consists of 4 components:
 * the lexer: tokenizes the input stream into a stream of `Plitz\Lexer\Tokens`
 * the parser: parses a `Plitz\Lexer\TokenStream` and informs a `Plitz\Parser\Visitor` of the parsed blocks
 * the compiler: implements the `Plitz\Parser\Visitor` class and writes code to an output stream
 * the Blitz compatibility layer: wraps all the parts above up in a single class while trying to maintain source-compatibility with `Blitz`
