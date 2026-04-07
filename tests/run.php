<?php
/**
 * tests/run.php
 *
 * Minimal test runner — no external libraries required.
 * Runs QueryTest (unit tests) and DatabaseTest (integration tests).
 *
 * Usage:
 *   php tests/run.php
 *
 * Exit code 0 when all tests pass, 1 when any test fails.
 *
 * @author DavidPerez-2357
 * @link   https://github.com/DavidPerez-2357/DatabaseMethods
 */

// Load all library classes (Query, Database, Mysql, Postgres, Sqlite, Sql).
require_once __DIR__ . '/../DatabaseMethods.php';
require_once __DIR__ . '/queryTest.php';
require_once __DIR__ . '/DatabaseTest.php';

// ---------------------------------------------------------------------------
// Custom exception used by the assertion helpers below
// ---------------------------------------------------------------------------

class TestAssertionException extends RuntimeException {}

// ---------------------------------------------------------------------------
// Assertion helpers
// ---------------------------------------------------------------------------

/**
 * Asserts strict equality between $expected and $actual.
 *
 * @throws TestAssertionException on failure.
 */
function assert_equals($expected, $actual, $msg = '')
{
    if ($expected !== $actual) {
        throw new TestAssertionException(
            $msg ?: sprintf(
                "Expected:\n  %s\nActual:\n  %s",
                var_export($expected, true),
                var_export($actual, true)
            )
        );
    }
}

/**
 * Asserts that $haystack contains the substring $needle.
 *
 * @throws TestAssertionException on failure.
 */
function assert_contains($needle, $haystack, $msg = '')
{
    if (strpos($haystack, $needle) === false) {
        throw new TestAssertionException(
            $msg ?: sprintf(
                "Expected string to contain:\n  %s\nbut got:\n  %s",
                var_export($needle, true),
                var_export($haystack, true)
            )
        );
    }
}

/**
 * Asserts that $haystack does NOT contain the substring $needle.
 *
 * @throws TestAssertionException on failure.
 */
function assert_not_contains($needle, $haystack, $msg = '')
{
    if (strpos($haystack, $needle) !== false) {
        throw new TestAssertionException(
            $msg ?: sprintf(
                "Expected string NOT to contain:\n  %s\nbut it does:\n  %s",
                var_export($needle, true),
                var_export($haystack, true)
            )
        );
    }
}

/**
 * Asserts that $value is strictly true.
 *
 * @throws TestAssertionException on failure.
 */
function assert_true($value, $msg = '')
{
    if ($value !== true) {
        throw new TestAssertionException(
            $msg ?: sprintf("Expected true but got: %s", var_export($value, true))
        );
    }
}

/**
 * Asserts that calling $fn throws an exception of exactly class $expectedClass
 * (or a subclass of it).
 *
 * @param string   $expectedClass Fully-qualified exception class name.
 * @param callable $fn
 * @param string   $msg           Optional failure message.
 *
 * @throws TestAssertionException on failure.
 */
function assert_throws($expectedClass, $fn, $msg = '')
{
    try {
        call_user_func($fn);
    } catch (Exception $e) {
        if (!($e instanceof $expectedClass)) {
            throw new TestAssertionException(
                $msg ?: sprintf(
                    "Expected %s but got %s: %s",
                    $expectedClass,
                    get_class($e),
                    $e->getMessage()
                )
            );
        }
        return; // Expected exception was thrown — test passes
    }

    throw new TestAssertionException(
        $msg ?: "Expected $expectedClass to be thrown but no exception was thrown"
    );
}

// ---------------------------------------------------------------------------
// Discover and run all test*() methods on the QueryTest class
// ---------------------------------------------------------------------------

$suite   = new QueryTest();
$methods = get_class_methods($suite);
sort($methods);

$passed  = 0;
$failed  = 0;
$results = [];

foreach ($methods as $method) {
    if (strncmp($method, 'test', 4) !== 0) {
        continue;
    }
    try {
        $suite->$method();
        $results[] = '[PASS] ' . $method;
        $passed++;
    } catch (TestAssertionException $e) {
        $results[] = '[FAIL] ' . $method . "\n"
            . '       ' . str_replace("\n", "\n       ", $e->getMessage());
        $failed++;
    } catch (Exception $e) {
        $results[] = '[FAIL] ' . $method . "\n"
            . '       Unexpected ' . get_class($e) . ': ' . $e->getMessage();
        $failed++;
    }
}

foreach ($results as $line) {
    echo $line . "\n";
}

$total = $passed + $failed;
echo "\n" . str_repeat('-', 55) . "\n";
printf("Results: %d/%d tests passed", $passed, $total);
if ($failed > 0) {
    printf(", %d FAILED", $failed);
}
echo "\n" . str_repeat('-', 55) . "\n";

// ---------------------------------------------------------------------------
// Discover and run all test*() methods on the DatabaseTest class
// ---------------------------------------------------------------------------

echo "\n";

$dbSuite   = null;
$dbPassed  = 0;
$dbFailed  = 0;
$dbResults = [];

try {
    $dbSuite = new DatabaseTest();
} catch (Exception $e) {
    echo "[ERROR] DatabaseTest setup failed: " . $e->getMessage() . "\n";
    echo "        Integration tests were skipped.\n\n";
}

if ($dbSuite !== null) {
    $dbMethods = get_class_methods($dbSuite);
    sort($dbMethods);

    foreach ($dbMethods as $method) {
        if (strncmp($method, 'test', 4) !== 0) {
            continue;
        }
        try {
            $dbSuite->$method();
            $dbResults[] = '[PASS] ' . $method;
            $dbPassed++;
        } catch (TestAssertionException $e) {
            $dbResults[] = '[FAIL] ' . $method . "\n"
                . '       ' . str_replace("\n", "\n       ", $e->getMessage());
            $dbFailed++;
        } catch (Exception $e) {
            $dbResults[] = '[FAIL] ' . $method . "\n"
                . '       Unexpected ' . get_class($e) . ': ' . $e->getMessage();
            $dbFailed++;
        }
    }

    $dbSuite->teardown();

    foreach ($dbResults as $line) {
        echo $line . "\n";
    }

    $dbTotal = $dbPassed + $dbFailed;
    echo "\n" . str_repeat('-', 55) . "\n";
    printf("DB Results: %d/%d tests passed", $dbPassed, $dbTotal);
    if ($dbFailed > 0) {
        printf(", %d FAILED", $dbFailed);
    }
    echo "\n" . str_repeat('-', 55) . "\n";
}

exit(($failed + $dbFailed) > 0 ? 1 : 0);