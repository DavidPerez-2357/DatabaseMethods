<?php

/**
 * tests/SqlDialectTests.php
 *
 * Unit tests for SqlDialect implementations:
 *   - DefaultSqlDialect  (ANSI double-quotes, LIMIT/OFFSET pagination)
 *   - MysqlSqlDialect    (backtick quoting, inherits pagination)
 *   - SqlServerDialect   (ANSI quoting, SELECT TOP / OFFSET-FETCH pagination)
 */
class SqlDialectTests
{
    // =========================================================================
    // quoteIdentifier
    // =========================================================================

    public function testDefaultDialectQuotesWithDoubleQuotes()
    {
        $dialect = new DefaultSqlDialect();
        assert_equals('"order"', $dialect->quoteIdentifier('order'));
    }

    public function testDefaultDialectEscapesInternalDoubleQuotes()
    {
        $dialect = new DefaultSqlDialect();
        assert_equals('"say ""hello"""', $dialect->quoteIdentifier('say "hello"'));
    }

    public function testMysqlDialectQuotesWithBackticks()
    {
        $dialect = new MysqlSqlDialect();
        assert_equals('`order`', $dialect->quoteIdentifier('order'));
    }

    // =========================================================================
    // compileSelectTop
    // =========================================================================

    public function testDefaultDialectCompileSelectTopAlwaysReturnsEmpty()
    {
        $dialect = new DefaultSqlDialect();
        assert_equals('', $dialect->compileSelectTop(10, null));
    }

    public function testSqlServerDialectCompileSelectTopWithLimitOnly()
    {
        $dialect = new SqlServerDialect();
        assert_equals('TOP 10 ', $dialect->compileSelectTop(10, null));
    }

    public function testSqlServerDialectCompileSelectTopWithOffsetReturnsEmpty()
    {
        // When an offset is present, OFFSET/FETCH handles limiting; no TOP clause.
        $dialect = new SqlServerDialect();
        assert_equals('', $dialect->compileSelectTop(10, 5));
    }

    // =========================================================================
    // compilePagination
    // =========================================================================

    public function testDefaultDialectCompilesPaginationWithLimitAndOffset()
    {
        $dialect = new DefaultSqlDialect();
        assert_equals(' LIMIT 10 OFFSET 5', $dialect->compilePagination(10, 5, false));
    }

    public function testSqlServerDialectCompilesOffsetFetch()
    {
        $dialect = new SqlServerDialect();
        assert_equals(
            ' OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY',
            $dialect->compilePagination(10, 5, true)
        );
    }

    public function testSqlServerDialectThrowsWhenOffsetWithoutOrderBy()
    {
        $dialect = new SqlServerDialect();
        assert_throws('InvalidArgumentException', function () use ($dialect) {
            $dialect->compilePagination(null, 5, false);
        });
    }
}
