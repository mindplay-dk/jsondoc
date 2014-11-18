<?php

namespace mindplay\jsondoc\test;

use mindplay\jsondoc\DocumentStore;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use PHP_CodeCoverage;
use PHP_CodeCoverage_Exception;
use PHP_CodeCoverage_Report_Clover;
use PHP_CodeCoverage_Report_Text;

header('Content-type: text/plain');

require dirname(__DIR__) . '/vendor/autoload.php';

// FIXTURES:

class Foo
{
    public $bar;
}

/**
 * @return DocumentStore
 */
function createStore($db_path)
{
    $mask = umask(0);
    @mkdir($db_path, 0777, true);
    umask($mask);

    return new DocumentStore($db_path);
}

if (coverage()) {
    $filter = coverage()->filter();

    $filter->addDirectoryToWhitelist(dirname(__DIR__) . '/src');

    coverage()->start('test');
}

test(
    'Document store and session behavior',
    function () {
        $db_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'samplestore';

        rm_r($db_path); // clean up from a previous failed run

        $mask = umask(0);
        mkdir($db_path, 0777);
        umask($mask);

        $store = createStore($db_path);

        $session = $store->openSession('sampledb');

        $a = new Foo;
        $a->bar = 'one';

        $b = new Foo;
        $b->bar = 'two';

        $session->store($a, 'foo/a');
        $session->store($b, 'foo/b');

        ok($session->contains('foo/a'), 'first object correctly stored in session');
        eq($session->load('foo/a'), $a, 'first object retrieves from session');
        eq($session->load('foo/b'), $b, 'second object retrieved from session');
        eq($session->getId($a), 'foo/a', 'can get the ID of a stored object');

        $session->commit();

        eq($session->load('foo/a'), $a, 'can get first stored object after saving');
        eq($session->load('foo/b'), $b, 'can get second stored object after saving');
        eq($session->getId($b), 'foo/b', 'ca get the ID of a stored object');

        $session->close();

        unset($a);
        unset($b);

        ok(file_exists($db_path . '/sampledb/foo/a.json'), 'first document stored in the expected location');
        ok(file_exists($db_path . '/sampledb/foo/b.json'), 'second document stored in the expected location');

        $session = $store->openSession('sampledb');

        $a = $session->load('foo/a');
        $b = $session->load('foo/b');

        eq($a->bar, 'one', 'first stored object correctly retrieved');
        eq($b->bar, 'two', 'second stored object correctly retrieved');

        $session->delete('foo/a');

        ok($session->contains('foo/a'), 'object foo/a should remain in session until changes are committed');

        $session->commit();

        ok(! $session->contains('foo/a'), 'object foo/a was not automatically evicted after commit');

        $session->evict('foo/b');

        ok(! $session->contains('foo/b'), 'object foo/b has been evicted from the session');

        $session->commit();

        ok(! file_exists($db_path . '/sampledb/foo/a.json'), 'file foo/a.json should be deleted');

        ok (file_exists($db_path . '/sampledb/foo/b.json'), 'file foo/b.json should not be deleted');

        $session->close();

        $session = $store->openSession('sampledb');

        $c = new Foo();
        $c->bar = 'three';

        $session->store($c, 'foo/c');

        $session->delete('foo/b');

        $session->flush();

        eq($session->contains('foo/c'), false, 'store operation rolled back (object evicted)');
        eq($session->exists('foo/b'), true, 'delete operation rolled back (document still exists)');

        $session->close();

        rm_r($db_path); // clean up
    }
);

if (coverage()) {
    coverage()->stop();

    $report = new PHP_CodeCoverage_Report_Text(10, 90, false, false);
    echo $report->process(coverage(), false);

    $report = new PHP_CodeCoverage_Report_Clover();
    $report->process(coverage(), dirname(__DIR__) . '/build/logs/clover.xml');
}

exit(status());

# http://stackoverflow.com/a/3352564/283851

/**
 * Recursively delete a directory and all of it's contents - e.g.the equivalent of `rm -r` on the command-line.
 * Consistent with `rmdir()` and `unlink()`, an E_WARNING level error will be generated on failure.
 *
 * @param string $dir absolute path to directory to delete
 *
 * @return bool true on success; false on failure
 */
function rm_r($dir)
{
    if (false === file_exists($dir)) {
        return false;
    }

    /** @var SplFileInfo[] $files */
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        if ($fileinfo->isDir()) {
            if (false === rmdir($fileinfo->getRealPath())) {
                return false;
            }
        } else {
            if (false === unlink($fileinfo->getRealPath())) {
                return false;
            }
        }
    }

    return rmdir($dir);
}

// https://gist.github.com/mindplay-dk/4260582

/**
 * @param string   $name     test description
 * @param callable $function test implementation
 */
function test($name, $function)
{
    echo "\n=== $name ===\n\n";

    try {
        call_user_func($function);
    } catch (Exception $e) {
        ok(false, "UNEXPECTED EXCEPTION", $e);
    }
}

/**
 * @param bool   $result result of assertion
 * @param string $why    description of assertion
 * @param mixed  $value  optional value (displays on failure)
 */
function ok($result, $why = null, $value = null)
{
    if ($result === true) {
        echo "- PASS: " . ($why === null ? 'OK' : $why) . ($value === null ? '' : ' (' . format($value) . ')') . "\n";
    } else {
        echo "# FAIL: " . ($why === null ? 'ERROR' : $why) . ($value === null ? '' : ' - ' . format($value,
                    true)) . "\n";
        status(false);
    }
}

/**
 * @param mixed  $value    value
 * @param mixed  $expected expected value
 * @param string $why      description of assertion
 */
function eq($value, $expected, $why = null)
{
    $result = $value === $expected;

    $info = $result
        ? format($value)
        : "expected: " . format($expected, true) . ", got: " . format($value, true);

    ok($result, ($why === null ? $info : "$why ($info)"));
}

/**
 * @param string   $exception_type Exception type name
 * @param string   $why            description of assertion
 * @param callable $function       function expected to throw
 */
function expect($exception_type, $why, $function)
{
    try {
        call_user_func($function);
    } catch (Exception $e) {
        if ($e instanceof $exception_type) {
            ok(true, $why, $e);
            return;
        } else {
            $actual_type = get_class($e);
            ok(false, "$why (expected $exception_type but $actual_type was thrown)");
            return;
        }
    }

    ok(false, "$why (expected exception $exception_type was NOT thrown)");
}

/**
 * @param mixed $value
 * @param bool  $verbose
 *
 * @return string
 */
function format($value, $verbose = false)
{
    if ($value instanceof Exception) {
        return get_class($value)
        . ($verbose ? ": \"" . $value->getMessage() . "\"" : '');
    }

    if (!$verbose && is_array($value)) {
        return 'array[' . count($value) . ']';
    }

    if (is_bool($value)) {
        return $value ? 'TRUE' : 'FALSE';
    }

    if (is_object($value) && !$verbose) {
        return get_class($value);
    }

    return print_r($value, true);
}

/**
 * @param bool|null $status test status
 *
 * @return int number of failures
 */
function status($status = null)
{
    static $failures = 0;

    if ($status === false) {
        $failures += 1;
    }

    return $failures;
}

/**
 * @return PHP_CodeCoverage|null code coverage service, if available
 */
function coverage()
{
    static $coverage = null;

    if ($coverage === false) {
        return null; // code coverage unavailable
    }

    if ($coverage === null) {
        try {
            $coverage = new PHP_CodeCoverage;
        } catch (PHP_CodeCoverage_Exception $e) {
            echo "# Notice: no code coverage run-time available\n";
            $coverage = false;
            return null;
        }
    }

    return $coverage;
}
