<?php

/**
 * SQL dialect abstraction for driver-specific SQL compilation.
 */
interface SqlDialect
{
    /**
     * Compiles pagination SQL fragment.
     *
     * @param int|null $limit  Maximum rows to return, or null for no explicit limit.
     * @param int|null $offset Rows to skip, or null for no offset clause.
     * @return string SQL fragment prefixed with a leading space when not empty.
     */
    public function compilePagination($limit, $offset);
}

/**
 * Default SQL dialect used by MySQL, PostgreSQL, and SQLite.
 */
class DefaultSqlDialect implements SqlDialect
{
    public function compilePagination($limit, $offset)
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
 * SQL Server dialect for OFFSET/FETCH pagination syntax.
 */
class SqlServerDialect implements SqlDialect
{
    public function compilePagination($limit, $offset)
    {
        if ($limit === null && $offset === null) {
            return '';
        }

        $offset = $offset !== null ? (int) $offset : 0;
        $sql = ' OFFSET ' . $offset . ' ROWS';

        if ($limit !== null) {
            $sql .= ' FETCH NEXT ' . (int) $limit . ' ROWS ONLY';
        }

        return $sql;
    }
}
