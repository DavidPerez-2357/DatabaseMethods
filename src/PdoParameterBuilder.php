<?php

/**
 * PdoParameterBuilder.php
 *
 * Static utility for generating SQL fragments and PDO named parameters.
 *
 * @author DavidPerez-2357
 * @link https://github.com/DavidPerez-2357/DatabaseMethods
 */

/**
 * Utility class for generating PDO named-parameter placeholders and SQL fragments.
 * All methods are static; no state, no dependencies on Database or Query.
 *
 * @package DatabaseMethods
 */
class PdoParameterBuilder
{
    /**
     * Builds an AND-joined equality SQL fragment and matching PDO params from a column => value map.
     * NULL values produce "col IS NULL" and are omitted from the params array.
     * Column names are validated as plain SQL identifiers.
     *
     * @param array  $conditions Associative array of column => value pairs.
     * @param string $prefix     Optional prefix for placeholder names (e.g. 'w_' → ':w_col').
     * @throws InvalidArgumentException If any column name is not a valid unqualified identifier.
     * @return array [string $sql, array $params]
     *
     * @example
     * ```php
     * // buildEquality(['id' => 5, 'email' => 'a@b.com'], 'w_')
     * // => ['id = :w_id AND email = :w_email', [':w_id' => 5, ':w_email' => 'a@b.com']]
     *
     * // buildEquality(['deleted_at' => null])
     * // => ['deleted_at IS NULL', []]
     * ```
     */
    public static function buildEquality(array $conditions, $prefix = '')
    {
        $parts  = array();
        $params = array();

        foreach ($conditions as $col => $value) {
            Query::validateUnqualifiedIdentifier($col, 'condition column');

            if ($value === null) {
                $parts[] = "{$col} IS NULL";
            } else {
                $placeholder    = ':' . $prefix . $col;
                $parts[]        = "{$col} = {$placeholder}";
                $params[$placeholder] = $value;
            }
        }

        return array(implode(' AND ', $parts), $params);
    }

    /**
     * Builds a PDO named-parameter array from an indexed list of values.
     * Keys are re-indexed before placeholder names are assigned.
     *
     * @param array  $values Array of values to parameterize.
     * @param string $prefix Optional prefix for placeholder names (e.g. 'ids_' → ':ids_0').
     * @return array Associative array mapping ':prefix_N' => value.
     *
     * @example
     * ```php
     * // buildValues([1, 2, 3], 'ids_')
     * // => [':ids_0' => 1, ':ids_1' => 2, ':ids_2' => 3]
     * ```
     */
    public static function buildValues(array $values, $prefix = '')
    {
        $params = array();

        foreach (array_values($values) as $i => $value) {
            $params[':' . $prefix . $i] = $value;
        }

        return $params;
    }

    /**
     * Builds a PDO named-parameter array from a column => value map.
     * NULL values are included as-is. Column names are validated as plain SQL identifiers.
     *
     * @param array  $data   Associative array of column => value pairs.
     * @param string $prefix Optional prefix for placeholder names (e.g. 'set_' → ':set_col').
     * @throws InvalidArgumentException If any column name is not a valid unqualified identifier.
     * @return array Associative array mapping ':prefix_col' => value.
     *
     * @example
     * ```php
     * // buildNamedParams(['name' => 'Alice', 'age' => 30])
     * // => [':name' => 'Alice', ':age' => 30]
     * ```
     */
    public static function buildNamedParams(array $data, $prefix = '')
    {
        $params = array();

        foreach ($data as $col => $value) {
            Query::validateUnqualifiedIdentifier($col, 'parameter column');
            $params[':' . $prefix . $col] = $value;
        }

        return $params;
    }

    /**
     * Builds the SQL SET fragment for an UPDATE statement from an array of column names.
     * Column names are validated as plain SQL identifiers.
     *
     * @param array $fields Non-empty array of column names.
     * @throws InvalidArgumentException If $fields is empty or any name fails identifier validation.
     * @return string Comma-separated SET fragment.
     *
     * @example
     * ```php
     * // buildSetClause(['name', 'email'])
     * // => 'name = :name, email = :email'
     * ```
     */
    public static function buildSetClause(array $fields)
    {
        if (empty($fields)) {
            throw new InvalidArgumentException('buildSetClause() requires at least one field.');
        }

        $clauses = array();
        foreach ($fields as $col) {
            Query::validateUnqualifiedIdentifier($col, 'SET field');
            $clauses[] = "{$col} = :{$col}";
        }

        return implode(', ', $clauses);
    }

    /**
     * Builds the per-row placeholder groups for a multi-row INSERT VALUES clause.
     * Column names are validated as plain SQL identifiers.
     *
     * @param array $fields   Non-empty array of column names.
     * @param int   $rowCount Number of rows (must be >= 1).
     * @throws InvalidArgumentException If $fields is empty or $rowCount < 1.
     * @return array Array of row-group strings.
     *
     * @example
     * ```php
     * // buildInsertPlaceholders(['name', 'email'], 2)
     * // => ['(:name_0, :email_0)', '(:name_1, :email_1)']
     * ```
     */
    public static function buildInsertPlaceholders(array $fields, $rowCount)
    {
        if (empty($fields)) {
            throw new InvalidArgumentException('buildInsertPlaceholders() requires at least one field.');
        }

        if (!is_int($rowCount) || $rowCount < 1) {
            throw new InvalidArgumentException('buildInsertPlaceholders() requires $rowCount to be a positive integer.');
        }

        foreach ($fields as $col) {
            Query::validateUnqualifiedIdentifier($col, 'INSERT field');
        }

        $groups = array();
        for ($i = 0; $i < $rowCount; $i++) {
            $row = array();
            foreach ($fields as $col) {
                $row[] = ":{$col}_{$i}";
            }
            $groups[] = '(' . implode(', ', $row) . ')';
        }

        return $groups;
    }

    /**
     * Builds the flat PDO named-parameter map for a multi-row INSERT from an array of row arrays.
     * Each key uses the form ':col_N' (N = zero-based row index).
     * Column names are validated as plain SQL identifiers.
     *
     * @param array $rows Non-empty array of associative arrays (each row must have the same keys).
     * @throws InvalidArgumentException If $rows is empty or any column name fails identifier validation.
     * @return array Flat params map, e.g. [':name_0' => 'Alice', ':name_1' => 'Bob'].
     *
     * @example
     * ```php
     * // buildInsertParams([['name' => 'Alice', 'age' => 30], ['name' => 'Bob', 'age' => 25]])
     * // => [':name_0' => 'Alice', ':age_0' => 30, ':name_1' => 'Bob', ':age_1' => 25]
     * ```
     */
    public static function buildInsertParams(array $rows)
    {
        if (empty($rows)) {
            throw new InvalidArgumentException('buildInsertParams() requires at least one row.');
        }

        $params = array();
        foreach (array_values($rows) as $i => $row) {
            foreach ($row as $col => $value) {
                Query::validateUnqualifiedIdentifier($col, 'INSERT field');
                $params[":{$col}_{$i}"] = $value;
            }
        }

        return $params;
    }
}
