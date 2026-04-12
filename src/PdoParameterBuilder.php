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
 * All methods are static and the class maintains no state. Identifier validation
 * is handled internally by this class.
 *
 * @package DatabaseMethods
 */
class PdoParameterBuilder
{
    /** Regex that matches a plain SQL identifier: letter/underscore-first, then alphanumeric/underscores. */
    const IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /** Regex that matches a plain or table-qualified SQL identifier (e.g. 'col' or 'alias.col'). */
    const QUALIFIED_IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/';
    /**
     * Builds an AND-joined equality SQL fragment and matching PDO params from a column => value map.
     * NULL values produce "col IS NULL" and are omitted from the params array.
     * Column names may be plain (e.g. 'email') or table-qualified (e.g. 'u.email').
     * Dots in qualified names are replaced with underscores in the placeholder (e.g. 'u.email' → ':u_email').
     * If two column names produce the same placeholder after substitution, an exception is thrown.
     *
     * @param array  $conditions Associative array of column => value pairs.
     * @param string $prefix     Optional prefix for placeholder names (e.g. 'w_' → ':w_col').
     *                           If non-empty, it must be a valid identifier (letter/underscore first).
     * @throws InvalidArgumentException If any column name is not a valid plain or qualified identifier,
     *                                  if the prefix is non-empty but not a valid identifier,
     *                                  or if two columns produce the same placeholder name.
     * @return array [string $sql, array $params]
     *
     * @example
     * ```php
     * // buildEquality(['id' => 5, 'email' => 'a@b.com'], 'w_')
     * // => ['id = :w_id AND email = :w_email', [':w_id' => 5, ':w_email' => 'a@b.com']]
     *
     * // buildEquality(['deleted_at' => null])
     * // => ['deleted_at IS NULL', []]
     *
     * // buildEquality(['u.id' => 5, 'u.deleted_at' => null])
     * // => ['u.id = :u_id AND u.deleted_at IS NULL', [':u_id' => 5]]
     * ```
     */
    public static function buildEquality(array $conditions, $prefix = '')
    {
        if ($prefix !== '' && !preg_match(self::IDENTIFIER_PATTERN, $prefix)) {
            throw new InvalidArgumentException(
                "Invalid prefix for buildEquality(): must start with a letter or underscore and contain only"
                . " alphanumeric characters and underscores."
            );
        }

        $parts            = array();
        $params           = array();
        $seenPlaceholders = array();

        foreach ($conditions as $col => $value) {
            self::validateQualifiedIdentifier($col, 'condition column');

            if ($value === null) {
                $parts[] = "{$col} IS NULL";
            } else {
                $name = $prefix . self::toPlaceholderName($col);

                if (isset($seenPlaceholders[$name])) {
                    throw new InvalidArgumentException(
                        "Placeholder name collision: '{$name}' is produced by more than one column after"
                        . " dot-to-underscore substitution."
                    );
                }

                $seenPlaceholders[$name] = true;
                $placeholder             = ':' . $name;
                $parts[]                 = "{$col} = {$placeholder}";
                $params[$placeholder]    = $value;
            }
        }

        return array(implode(' AND ', $parts), $params);
    }

    /**
     * Builds a PDO named-parameter array from an indexed list of values.
     * Keys are re-indexed before placeholder names are assigned.
     *
     * @param array  $values Array of values to parameterize.
     * @param string $prefix Prefix for placeholder names (e.g. 'ids_' → ':ids_0').
     *                       Must be non-empty when $values is non-empty, and must start with a letter
     *                       or underscore (e.g. 'id_'). An empty prefix with non-empty $values is
     *                       rejected because it would produce invalid PDO named placeholder keys
     *                       like ':0', ':1'.
     * @throws InvalidArgumentException If $values is non-empty and $prefix is empty or not a valid identifier.
     * @return array Associative array mapping ':prefixN' => value.
     *
     * @example
     * ```php
     * // buildValues([1, 2, 3], 'ids_')
     * // => [':ids_0' => 1, ':ids_1' => 2, ':ids_2' => 3]
     * ```
     */
    public static function buildValues(array $values, $prefix = '')
    {
        if (!empty($values)) {
            if ($prefix === '') {
                throw new InvalidArgumentException(
                    'buildValues() requires a non-empty $prefix when $values is non-empty;'
                    . ' an empty prefix produces invalid PDO named placeholder keys like ":0".'
                );
            }

            if (!preg_match(self::IDENTIFIER_PATTERN, $prefix)) {
                throw new InvalidArgumentException(
                    "Invalid prefix for buildValues(): must start with a letter or underscore and contain only"
                    . " alphanumeric characters and underscores."
                );
            }
        }

        $params = array();

        foreach (array_values($values) as $i => $value) {
            $params[':' . $prefix . $i] = $value;
        }

        return $params;
    }

    /**
     * Builds a PDO named-parameter array from a column => value map.
     * NULL values are included as-is.
     * Column names may be plain (e.g. 'email') or table-qualified (e.g. 'u.email').
     * Dots in qualified names are replaced with underscores in the placeholder (e.g. 'u.email' → ':u_email').
     * If two column names produce the same placeholder after substitution, an exception is thrown.
     *
     * @param array  $data   Associative array of column => value pairs.
     * @param string $prefix Optional prefix for placeholder names (e.g. 'set_' → ':set_col').
     *                       If non-empty, it must be a valid identifier (letter/underscore first).
     * @throws InvalidArgumentException If any column name is not a valid plain or qualified identifier,
     *                                  if the prefix is non-empty but not a valid identifier,
     *                                  or if two columns produce the same placeholder name.
     * @return array Associative array mapping ':prefixCol' => value.
     *
     * @example
     * ```php
     * // buildNamedParams(['name' => 'Alice', 'age' => 30])
     * // => [':name' => 'Alice', ':age' => 30]
     *
     * // buildNamedParams(['u.name' => 'Alice', 'u.age' => 30])
     * // => [':u_name' => 'Alice', ':u_age' => 30]
     * ```
     */
    public static function buildNamedParams(array $data, $prefix = '')
    {
        if ($prefix !== '' && !preg_match(self::IDENTIFIER_PATTERN, $prefix)) {
            throw new InvalidArgumentException(
                "Invalid prefix for buildNamedParams(): must start with a letter or underscore and contain only"
                . " alphanumeric characters and underscores."
            );
        }

        $params           = array();
        $seenPlaceholders = array();

        foreach ($data as $col => $value) {
            self::validateQualifiedIdentifier($col, 'parameter column');

            $name = $prefix . self::toPlaceholderName($col);

            if (isset($seenPlaceholders[$name])) {
                throw new InvalidArgumentException(
                    "Placeholder name collision: '{$name}' is produced by more than one column after"
                    . " dot-to-underscore substitution."
                );
            }

            $seenPlaceholders[$name] = true;
            $params[':' . $name]     = $value;
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
            self::validateIdentifier($col, 'SET field');
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
            self::validateIdentifier($col, 'INSERT field');
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
     * @param array $rows Non-empty array of associative arrays (each row must have the same key set as the first row; key order may differ).
     * @throws InvalidArgumentException If $rows is empty, if any row's key set does not match the first row, or if any column name fails identifier validation.
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

        $rows = array_values($rows);

        if (!is_array($rows[0])) {
            throw new InvalidArgumentException('buildInsertParams() requires each row to be an associative array.');
        }

        if (empty($rows[0])) {
            throw new InvalidArgumentException('buildInsertParams() requires each row to contain at least one field.');
        }

        $fields = array_keys($rows[0]);
        foreach ($fields as $col) {
            self::validateIdentifier($col, 'INSERT field');
        }

        $params = array();
        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('buildInsertParams() requires each row to be an associative array.');
            }

            $rowFields = array_keys($row);
            if (
                count($rowFields) !== count($fields) ||
                array_diff($fields, $rowFields) ||
                array_diff($rowFields, $fields)
            ) {
                throw new InvalidArgumentException('buildInsertParams() requires every row to have the same fields.');
            }

            foreach ($fields as $col) {
                $params[":{$col}_{$i}"] = $row[$col];
            }
        }

        return $params;
    }

    /**
     * Validates that $name is a plain SQL identifier (letter/underscore first, then alphanumeric/underscores).
     *
     * @param string $name    The identifier to validate.
     * @param string $context Human-readable label used in the exception message.
     * @throws InvalidArgumentException If $name is not a valid unqualified identifier.
     */
    private static function validateIdentifier($name, $context)
    {
        if (!is_string($name) || !preg_match(self::IDENTIFIER_PATTERN, $name)) {
            throw new InvalidArgumentException(
                "Invalid {$context}: must start with a letter or underscore and contain only"
                . " alphanumeric characters and underscores (unqualified column name, e.g. 'email' or 'created_at')."
            );
        }
    }

    /**
     * Validates that $name is a plain or qualified SQL identifier (e.g. 'col' or 'alias.col').
     *
     * @param string $name    The identifier to validate.
     * @param string $context Human-readable label used in the exception message.
     * @throws InvalidArgumentException If $name is not a valid identifier.
     */
    private static function validateQualifiedIdentifier($name, $context)
    {
        if (!is_string($name) || !preg_match(self::QUALIFIED_IDENTIFIER_PATTERN, $name)) {
            throw new InvalidArgumentException(
                "Invalid {$context}: expected an unqualified name (e.g. 'email') or a"
                . " qualified name (e.g. 'u.email'); only letters, digits, underscores and one optional dot are allowed."
            );
        }
    }

    /**
     * Converts a column name to a safe PDO placeholder name by replacing '.' with '_'.
     * E.g. 'u.email' becomes 'u_email'.
     *
     * @param string $col Column name (unqualified or qualified).
     * @return string Safe placeholder name.
     */
    private static function toPlaceholderName($col)
    {
        return str_replace('.', '_', $col);
    }
}
