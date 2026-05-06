<?php

/**
 * SQL dialect abstraction for driver-specific SQL compilation.
 */
interface SqlDialect
{
    /**
     * Quotes a SQL identifier (table or column name) using the dialect's quoting characters.
     *
     * @param string $identifier A single identifier segment (no dot/alias parsing).
     * @return string
     */
    public function quoteIdentifier($identifier);

    /**
     * Returns a SELECT-clause prefix for row-limiting (e.g. 'TOP 10 ' on SQL Server).
     * Called before the field list; returns an empty string when not needed.
     *
     * @param int|null $limit  Maximum rows to return, or null when no limit is set.
     * @param int|null $offset Rows to skip, or null when no offset is set.
     * @return string Prefix to insert after 'SELECT ', including a trailing space when not empty.
     */
    public function compileSelectTop($limit, $offset);

    /**
     * Compiles pagination SQL fragment appended after the ORDER BY clause.
     *
     * @param int|null $limit      Maximum rows to return, or null for no explicit limit.
     * @param int|null $offset     Rows to skip, or null for no offset clause.
     * @param bool     $hasOrderBy Whether an ORDER BY clause is present in the query.
     * @throws InvalidArgumentException when the dialect requires ORDER BY but it is absent.
     * @return string SQL fragment prefixed with a leading space when not empty.
     */
    public function compilePagination($limit, $offset, $hasOrderBy);
}

/**
 * Default SQL dialect for ANSI-style compilation used by PostgreSQL and SQLite.
 * Quotes identifiers with ANSI double-quotes.
 *
 * SQL Server uses its own dialect class (SqlServerDialect) for pagination behavior,
 * even though it shares ANSI double-quote identifier quoting here.
 */
class DefaultSqlDialect implements SqlDialect
{
    public function quoteIdentifier($identifier)
    {
        return '"' . str_replace('"', '""', (string) $identifier) . '"';
    }

    public function compileSelectTop($limit, $offset)
    {
        return '';
    }

    public function compilePagination($limit, $offset, $hasOrderBy)
    {
        $sql = '';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        if ($offset !== null) {
            $sql .= ' OFFSET ' . (int) $offset;
        }

        return $sql;
    }
}

/**
 * MySQL SQL dialect.
 * Quotes identifiers with backticks.
 */
class MysqlSqlDialect extends DefaultSqlDialect
{
    public function quoteIdentifier($identifier)
    {
        return '`' . str_replace('`', '``', (string) $identifier) . '`';
    }
}

/**
 * SQL Server dialect.
 *
 * Pagination strategy:
 *  - limit only (no offset): rendered as SELECT TOP n in the SELECT clause.
 *  - offset (with or without limit): rendered as OFFSET/FETCH after ORDER BY.
 *    SQL Server requires ORDER BY for OFFSET/FETCH; an InvalidArgumentException
 *    is thrown when offset is used without an ORDER BY clause.
 */
class SqlServerDialect extends DefaultSqlDialect
{
    /**
     * Returns 'TOP n ' when only a limit is requested (no offset).
     * When an offset is also present, OFFSET/FETCH handles row limiting
     * and this method returns an empty string.
     */
    public function compileSelectTop($limit, $offset)
    {
        if ($limit !== null && $offset === null) {
            return 'TOP ' . (int) $limit . ' ';
        }

        return '';
    }

    /**
     * Compiles OFFSET/FETCH pagination for SQL Server.
     *
     * When only a limit is set (no offset), SELECT TOP already handles it
     * and this method returns an empty string.
     *
     * @throws InvalidArgumentException when offset is used without ORDER BY.
     */
    public function compilePagination($limit, $offset, $hasOrderBy)
    {
        // Limit-only is handled by SELECT TOP n; nothing to append.
        if ($offset === null) {
            return '';
        }

        // OFFSET/FETCH requires ORDER BY in SQL Server.
        if (!$hasOrderBy) {
            throw new InvalidArgumentException(
                'SQL Server requires an ORDER BY clause when using OFFSET pagination.'
            );
        }

        $sql = ' OFFSET ' . (int) $offset . ' ROWS';

        if ($limit !== null) {
            $sql .= ' FETCH NEXT ' . (int) $limit . ' ROWS ONLY';
        }

        return $sql;
    }
}
