<?php

/**
 * SqlValidator.php
 *
 * Centralised SQL naming and expression validation utility.
 *
 * @author DavidPerez-2357
 * @link https://github.com/DavidPerez-2357/DatabaseMethods
 */

/**
 * Static utility class for validating SQL identifiers and expressions.
 * Provides shared regex constants and assert helpers reusable across the library.
 *
 * @package DatabaseMethods
 */
class SqlValidator
{
    /**
     * Regex that matches a plain SQL identifier:
     * letter/underscore first, then alphanumeric/underscores.
     * E.g. 'users', 'created_at'.
     */
    const IDENTIFIER_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /**
     * Regex that matches a plain or schema-qualified SQL identifier.
     * E.g. 'users', 'public.users', 'dbo.orders'.
     */
    const QUALIFIED_IDENTIFIER_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/';

    /**
     * Regex that matches a table expression with an optional alias.
     * Supports: 'users', 'users u', 'users AS u', 'public.users u', 'public.users AS u'.
     * Case-insensitive for the AS keyword.
     */
    const ALIAS_IDENTIFIER_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?(\s+(?:AS\s+)?[a-zA-Z_][a-zA-Z0-9_]*)?$/i';

    /**
     * Regex that matches a comma-separated ORDER BY expression list.
     * Each item: [table.]column [ASC|DESC].
     * E.g. 'name ASC', 'created_at DESC', 'users.name ASC, email DESC'.
     * Case-insensitive for ASC/DESC.
     */
    const ORDER_BY_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?\s*(ASC|DESC)?(\s*,\s*[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?\s*(ASC|DESC)?)*$/i';

    /**
     * Regex that matches a comma-separated GROUP BY expression list.
     * Each item: [table.]column (no ASC/DESC allowed).
     * E.g. 'name', 'users.id', 'name, email'.
     */
    const GROUP_BY_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?(\s*,\s*[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?)*$/';

    /**
     * Asserts that $name is a plain (unqualified) SQL identifier.
     *
     * @param string $name    The value to validate.
     * @param string $context Human-readable label used in the exception message.
     * @throws InvalidArgumentException If $name is not a valid plain identifier.
     */
    public static function assertIdentifier($name, $context = 'identifier')
    {
        if (!is_string($name) || !preg_match(self::IDENTIFIER_REGEX, $name)) {
            throw new InvalidArgumentException(
                "Invalid {$context}: must start with a letter or underscore and contain only"
                . " alphanumeric characters and underscores (unqualified column name, e.g. 'email' or 'created_at')."
            );
        }
    }

    /**
     * Asserts that $name is a plain or schema-qualified SQL identifier.
     *
     * @param string $name    The value to validate.
     * @param string $context Human-readable label used in the exception message.
     * @throws InvalidArgumentException If $name is not a valid plain or qualified identifier.
     */
    public static function assertQualifiedIdentifier($name, $context = 'identifier')
    {
        if (!is_string($name) || !preg_match(self::QUALIFIED_IDENTIFIER_REGEX, $name)) {
            throw new InvalidArgumentException(
                "Invalid {$context}: expected an unqualified name (e.g. 'email') or a"
                . " qualified name (e.g. 'u.email'); only letters, digits, underscores and one optional dot are allowed."
            );
        }
    }

    /**
     * Asserts that $table is a valid SQL table name (plain or schema-qualified).
     *
     * @param string $table The table name to validate.
     * @throws InvalidArgumentException If $table is not a valid table name.
     */
    public static function assertTable($table)
    {
        if (!is_string($table) || !preg_match(self::QUALIFIED_IDENTIFIER_REGEX, $table)) {
            throw new InvalidArgumentException(
                "Invalid table name: only alphanumeric characters and underscores are allowed"
                . " (optionally schema-qualified, e.g. 'schema.table')."
            );
        }
    }

    /**
     * Asserts that $field is a valid plain (unqualified) SQL field name.
     *
     * @param string $field The field name to validate.
     * @throws InvalidArgumentException If $field is not a valid unqualified identifier.
     */
    public static function assertField($field)
    {
        if (!is_string($field) || !preg_match(self::IDENTIFIER_REGEX, $field)) {
            throw new InvalidArgumentException(
                "Invalid field name: must start with a letter or underscore and contain only"
                . " alphanumeric characters and underscores (unqualified column name, e.g. 'email' or 'created_at')."
            );
        }
    }

    /**
     * Asserts that $expr is a valid SQL ORDER BY expression.
     * Returns the trimmed, validated expression.
     *
     * @param string $expr The ORDER BY expression to validate.
     * @throws InvalidArgumentException If $expr is not a string or contains disallowed characters.
     * @return string The trimmed, validated ORDER BY expression.
     */
    public static function assertOrderBy($expr)
    {
        if (!is_string($expr)) {
            throw new InvalidArgumentException("order_by must be a string.");
        }

        $expr = trim($expr);

        if (!preg_match(self::ORDER_BY_REGEX, $expr)) {
            throw new InvalidArgumentException(
                "Invalid order_by value. Use column names with optional ASC/DESC, e.g. 'created_at DESC, id ASC'."
            );
        }

        return $expr;
    }

    /**
     * Asserts that $expr is a valid SQL GROUP BY expression.
     * Returns the trimmed, validated expression.
     *
     * @param string $expr The GROUP BY expression to validate.
     * @throws InvalidArgumentException If $expr is not a string or contains disallowed characters.
     * @return string The trimmed, validated GROUP BY expression.
     */
    public static function assertGroupBy($expr)
    {
        if (!is_string($expr)) {
            throw new InvalidArgumentException("group_by must be a string.");
        }

        $expr = trim($expr);

        if (!preg_match(self::GROUP_BY_REGEX, $expr)) {
            throw new InvalidArgumentException(
                "Invalid group_by value. Use plain column names, e.g. 'users.id' or 'id, name'."
            );
        }

        return $expr;
    }
}
