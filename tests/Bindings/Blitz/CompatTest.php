<?php
namespace Plitz\Tests\Bindings\Blitz;

class CompatTest
{
    private static function getBlitzTestsPath()
    {
        return realpath(__DIR__ . '/../../../vendor/alexeyrybak/blitz/tests/');
    }

    public static function suite()
    {
        $testSuite = new \PHPUnit_Framework_TestSuite();

        if (class_exists('\\Blitz', false)) {
            $skipTests = true;
        } else {
            $skipTests = false;
        }

        $blitzTestsPath = self::getBlitzTestsPath();

        $phpSettings = [
            'include_path=' . ini_get('include_path') . ':' . $blitzTestsPath
        ];

        $facade = new \File_Iterator_Facade();
        foreach ($facade->getFilesAsArray($blitzTestsPath, '.phpt') as $file) {
            if ($skipTests) {
                $testSuite->addTest(new \PHPUnit_Framework_SkippedTestCase(PhptTestCase::class, $file, 'Actual Blitz extension is loaded'));
            } else {
                // TODO: inject this codeblock in common.inc:
                // require_once '/Users/mcuelenaere/Projects/plitz/vendor/autoload.php';
                // class_alias('\\Plitz\\Bindings\\Blitz\\Blitz', '\\Blitz');
                $testSuite->addTest(new PhptTestCase($file, $phpSettings));
            }
        }

        return $testSuite;
    }
}
