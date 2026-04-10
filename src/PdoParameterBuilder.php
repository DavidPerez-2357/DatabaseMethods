<?php

/**
 * PdoParameterBuilder.php
 *
 * Standalone static utility for generating SQL fragments and PDO named parameters.
 * Centralizes placeholder creation so that Database and Query stay focused on their
 * own responsibilities (execution and SQL structure, respectively).
 *
 * @author DavidPerez-2357
 * @link https://github.com/DavidPerez-2357/DatabaseMethods
 */

/**
 * Utility class for generating PDO named-parameter placeholders and the
 * associated SQL fragments.
 *
 * All methods are static. The class has no state and no dependencies on
 * Database or Query — it can be used independently.
 *
 * @package DatabaseMethods
 */
class PdoParameterBuilder
{
    /**
     * Builds an equality SQL fragment and a matching PDO parameter array from an
     * associative column => value map.
     *
     * Each column name is validated as a plain (unqualified) SQL identifier via
     * Query::validateUnqualifiedIdentifier() to prevent SQL injection.
     *
     * NULL values are handled specially: instead of generating a named placeholder
     * that would be bound to NULL, the method emits an IS NULL clause in the SQL
     * fragment and omits the column from the returned $params array. This produces
     * valid SQL because `col = NULL` is always false in standard SQL, while
     * `col IS NULL` correctly matches NULL values.
     *
     * @param array  $conditions Associative array of column => value pairs.
     * @param string $prefix     Optional prefix applied to every placeholder name
     *                           (e.g. 'where_' produces ':where_col').
     *                           Must consist only of letters, digits, and underscores.
     * @throws InvalidArgumentException If any column name is not a valid unqualified identifier.
     * @return array Two-element array: [string $sql, array $params].
     *               $sql is the AND-joined equality fragment (empty string when $conditions is empty).
     *               $params maps ':prefix_col' => value for each non-NULL entry.
     *
     * @example
     * ```php
     * list($sql, $params) = PdoParameterBuilder::buildEquality(
     *     ['id' => 5, 'email' => 'john@email.com'],
     *     'where_'
     * );
     * // $sql   === 'id = :where_id AND email = :where_email'
     * // $params === [':where_id' => 5, ':where_email' => 'john@email.com']
     *
     * // NULL values produce IS NULL:
     * list($sql, $params) = PdoParameterBuilder::buildEquality(['deleted_at' => null]);
     * // $sql   === 'deleted_at IS NULL'
     * // $params === []
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
     *
     * Each value is stored under a key of the form ':prefix_N', where N is the
     * zero-based position in the (re-indexed) input array. This is intentionally
     * generic: the method only creates the placeholder map and does not assume any
     * particular SQL operator or clause.
     *
     * Typical uses include generating parameter maps for IN-list expressions or
     * multi-row INSERT placeholders when combined with SQL built elsewhere.
     *
     * @param array  $values Sequential (or any) array of values to parameterize.
     *                       Non-zero-based or non-sequential keys are re-indexed
     *                       before placeholder names are assigned.
     * @param string $prefix Optional prefix applied to every placeholder name
     *                       (e.g. 'ids_' produces ':ids_0', ':ids_1', …).
     * @return array Associative array mapping ':prefix_N' => value.
     *               Returns an empty array when $values is empty.
     *
     * @example
     * ```php
     * $params = PdoParameterBuilder::buildValues([1, 2, 3], 'ids_');
     * // [':ids_0' => 1, ':ids_1' => 2, ':ids_2' => 3]
     *
     * $params = PdoParameterBuilder::buildValues(['john@email.com'], 'email_');
     * // [':email_0' => 'john@email.com']
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
     * Builds a PDO named-parameter array from an associative column => value map,
     * giving each entry a placeholder key of the form ':prefix_col'.
     *
     * Each column name is validated as a plain (unqualified) SQL identifier via
     * Query::validateUnqualifiedIdentifier() to prevent injection through key names.
     *
     * Unlike buildEquality(), this method does NOT treat NULL specially — NULL values
     * are included in the returned array as-is so that the caller can bind them to
     * PDO::PARAM_NULL at execution time. Use this method when you only need the
     * parameter map (not the SQL fragment), for example when building UPDATE SET bindings.
     *
     * @param array  $data   Associative array of column => value pairs.
     * @param string $prefix Optional prefix applied to every placeholder name
     *                       (e.g. 'set_' produces ':set_col').
     * @throws InvalidArgumentException If any column name is not a valid unqualified identifier.
     * @return array Associative array mapping ':prefix_col' => value.
     *               Returns an empty array when $data is empty.
     *
     * @example
     * ```php
     * $params = PdoParameterBuilder::buildNamedParams(['name' => 'Alice', 'age' => 30]);
     * // [':name' => 'Alice', ':age' => 30]
     *
     * $params = PdoParameterBuilder::buildNamedParams(['name' => 'Alice'], 'set_');
     * // [':set_name' => 'Alice']
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
     *
     * Each column name is validated as a plain (unqualified) SQL identifier via
     * Query::validateUnqualifiedIdentifier(). Each column maps to a same-named PDO
     * placeholder (e.g. 'email' -> 'email = :email'). The resulting fragments are
     * joined by a comma and a space.
     *
     * @param array $fields Non-empty array of column names to include in the SET clause.
     * @throws InvalidArgumentException If $fields is empty or any name fails identifier validation.
     * @return string Comma-separated SET fragment, e.g. 'name = :name, email = :email'.
     *
     * @example
     * ```php
     * $sql = PdoParameterBuilder::buildSetClause(['name', 'email']);
     * // 'name = :name, email = :email'
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
     * Builds the per-row placeholder groups for a multi-row INSERT statement.
     *
     * For each row index from 0 to ($rowCount - 1) the method produces a parenthesised,
     * comma-separated list of named placeholders — one per field — in the form
     * ':col_N' (where N is the row index). The resulting array of row-group strings can
     * be joined with ', ' and embedded directly into the VALUES clause of an INSERT query.
     *
     * Each column name is validated as a plain (unqualified) SQL identifier via
     * Query::validateUnqualifiedIdentifier() to prevent injection.
     *
     * @param array $fields   Non-empty array of column names.
     * @param int   $rowCount Number of rows to generate placeholders for (must be >= 1).
     * @throws InvalidArgumentException If $fields is empty or $rowCount is less than 1.
     * @return array Array of row-group strings, e.g.
     *               ['(:name_0, :email_0)', '(:name_1, :email_1)'] for 2 rows.
     *
     * @example
     * ```php
     * $groups = PdoParameterBuilder::buildInsertPlaceholders(['name', 'email'], 2);
     * // ['(:name_0, :email_0)', '(:name_1, :email_1)']
     * $sql = 'INSERT INTO users (name, email) VALUES ' . implode(', ', $groups);
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
}
