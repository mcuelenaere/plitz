<?php
namespace Plitz\Tests\Parser;

use Plitz\Lexer\Lexer;
use Plitz\Parser\ParseException;
use Plitz\Parser\Parser;
use Symfony\Component\Yaml\Yaml;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    protected function tearDown()
    {
        \Mockery::close();
    }

    private function constructExpression($type, array $properties = [])
    {
        if (!preg_match('|Expression$|', $type)) {
            throw new \InvalidArgumentException("'$type' should be of format *Expression");
        }

        $className = '\\Plitz\\Parser\\Expressions\\' . preg_replace('|Expression$|', '', $type);
        $reflector = new \ReflectionClass($className);
        $constructor = $reflector->getConstructor();

        $constructorArguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            if (!isset($properties[$parameter->getName()])) {
                if (!$parameter->isOptional()) {
                    throw new \InvalidArgumentException("Required parameter {$parameter->getName()} not given when constructing $type");
                }

                $constructorArguments[$parameter->getPosition()] = $parameter->getDefaultValue();
            } else {
                $propertyValue = $properties[$parameter->getName()];
                if (is_array($propertyValue)) {
                    $propertyValue = $this->processVisitorYaml($propertyValue);
                }

                $constructorArguments[$parameter->getPosition()] = $propertyValue;
            }
        }

        return $reflector->newInstanceArgs($constructorArguments);
    }

    private function processVisitorYaml(array $yaml)
    {
        if (count($yaml) === 1 && preg_match('|Expression$|', key($yaml))) {
            return $this->constructExpression(key($yaml), current($yaml));
        }

        foreach ($yaml as $key => &$value) {
            if (is_array($value)) {
                $value = $this->processVisitorYaml($value);
            }
        }

        return $yaml;
    }

    /**
     * @dataProvider provideYamlCases
     * @param string $filename
     * @param string $template
     * @param array $expectedVisitorCalls
     */
    public function testYamlCases($filename, $template, array $expectedVisitorCalls)
    {
        // create lexer
        $stream = fopen("php://temp", "r+");
        $lexer = new Lexer($stream, $filename . '[template]');

        // write data to lexer stream
        fwrite($stream, $template);
        fseek($stream, 0, SEEK_SET);

        // build expected chain
        $expectedCallChain = [];
        $expectedException = null;
        foreach ($expectedVisitorCalls as $visitorCall) {
            if ($expectedException !== null) {
                throw new \InvalidArgumentException("Test contains expected method calls after exception was thrown!");
            }

            $methodName = key($visitorCall);
            $methodArgs = current($visitorCall);

            if ($methodName === 'exception') {
                $expectedException = $methodArgs;
                continue;
            }

            $expectedCallChain[] = [
                'method' => $methodName,
                'arguments' => is_array($methodArgs) ? $this->processVisitorYaml($methodArgs) : [],
            ];
        }

        // build parser & lex+parse
        $visitor = new ArrayVisitor();
        $parser = new Parser($lexer->lex(), $visitor);
        try {
            $parser->parse();
        } catch (ParseException $ex) {
            if ($expectedException !== null) {
                $this->assertStringMatchesFormat($expectedException, $ex->getMessage());
            } else {
                throw $ex;
            }
        }

        $this->assertEquals($expectedCallChain, $visitor->getCalls());
    }

    public function provideYamlCases()
    {
        foreach (glob(dirname(__FILE__) . "/ParserTestCases/*.yaml") as $file) {
            $parsed = Yaml::parse(file_get_contents($file), true);
            yield basename($file) => [
                $file,
                $parsed['template'],
                $parsed['visitor']
            ];
        }
    }
}
