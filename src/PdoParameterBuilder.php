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
}
