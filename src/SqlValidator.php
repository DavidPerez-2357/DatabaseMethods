<?php

/**
 * SqlValidator.php
 *
 * Centralized SQL naming and expression validation utility.
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
     * Base identifier segment (no delimiters/anchors): letter/underscore first,
     * then alphanumeric/underscores.  Repeated literally in every regex below
     * because PHP 5.4 class constants do not support expression initializers.
     *
     * Plain segment:        [a-zA-Z_][a-zA-Z0-9_]*
     * ANSI-quoted segment:  "(?:[^"]|"")+"
     * Backtick segment:     `(?:[^`]|``)+`
     *
     * Combined segment (SEG):
     *   (?:[a-zA-Z_][a-zA-Z0-9_]*|"(?:[^"]|"")+"|`(?:[^`]|``)+`)
     */

    /**
     * Regex that matches a plain SQL identifier.
     * Used for PDO parameter names and INSERT/UPDATE column names that must be unquoted.
     * E.g. 'users', 'created_at'.
     */
    const IDENTIFIER_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /**
     * Regex that matches a single-segment SQL column identifier: either a plain
     * identifier or a quoted identifier (ANSI double-quotes or backticks) whose
     * inner content is a plain identifier.  No schema-qualification (dot) allowed.
     * Used for INSERT/UPDATE column lists where quoted names must still yield a
     * valid PDO placeholder after the quotes are stripped.
     * E.g. 'email', '"order"', '`from`'.
     */
    const COLUMN_IDENTIFIER_REGEX = '/^(?:[a-zA-Z_][a-zA-Z0-9_]*|"[a-zA-Z_][a-zA-Z0-9_]*"|`[a-zA-Z_][a-zA-Z0-9_]*`)$/';

    /**
     * Regex that matches a plain, ANSI-quoted, or backtick-quoted SQL identifier,
     * optionally schema-qualified (two segments separated by a dot).
     * E.g. 'users', '"order"', '`order`', 'public.users', '"public"."order"'.
     *
     * Both ANSI double-quotes and MySQL backticks are accepted because this
     * validator is dialect-agnostic; it cannot know which quote style is valid
     * for the active database.  Always use `$db->quote()` / `Query::quote()`
     * to produce the dialect-correct quoted form, which will then pass this check.
     */
    const QUALIFIED_IDENTIFIER_REGEX = '/^(?:[a-zA-Z_][a-zA-Z0-9_]*|"(?:[^"]|"")+"|`(?:[^`]|``)+`)(?:\.(?:[a-zA-Z_][a-zA-Z0-9_]*|"(?:[^"]|"")+"|`(?:[^`]|``)+`))?$/';

    /**
     * Regex that matches a table expression with an optional plain alias.
     * Accepts plain, ANSI-quoted, or backtick-quoted table names, optionally
     * schema-qualified, with an optional plain alias (AS keyword optional).
     * E.g. 'users', '"order"', '`order` o', '"public"."order" AS o'.
     *
     * Both ANSI double-quotes and MySQL backticks are accepted because this
     * validator is dialect-agnostic.  Use `$db->quote()` to produce the
     * dialect-correct quoted form.
     */
    const ALIAS_IDENTIFIER_REGEX = '/^(?:[a-zA-Z_][a-zA-Z0-9_]*|"(?:[^"]|"")+"|`(?:[^`]|``)+`)(?:\.(?:[a-zA-Z_][a-zA-Z0-9_]*|"(?:[^"]|"")+"|`(?:[^`]|``)+`))?(?:\s+(?:AS\s+)?[a-zA-Z_][a-zA-Z0-9_]*)?$/i';

    /**
     * Regex that matches a comma-separated ORDER BY expression list.
     * Each item is a (qualified) plain, ANSI-quoted, or backtick-quoted identifier
     * with an optional ASC / DESC direction.
     * E.g. '"order" ASC', '`name` DESC, id ASC'.
     *
     * Both ANSI double-quotes and MySQL backticks are accepted because this
     * validator is dialect-agnostic.  Use `$db->quote()` to produce the
     * dialect-correct quoted form.
     */
    const ORDER_BY_REGEX = '/^(?:[a-zA-Z_][a-zA-Z0-9_]*|"(?:[^"]|"")+"|`(?:[^`]|``)+`)(?:\.(?:[a-zA-Z_][a-zA-Z0-9_]*|"(?:[^"]|"")+"|`(?:[^`]|``)+`))?\s*(ASC|DESC)?(?:\s*,\s*(?:[a-zA-Z_][a-zA-Z0-9_]*|"(?:[^"]|"")+"|`(?:[^`]|``)+`)(?:\.(?:[a-zA-Z_][a-zA-Z0-9_]*|"(?:[^"]|"")+"|`(?:[^`]|``)+`))?\s*(ASC|DESC)?)*$/i';

    /**
     * Regex that matches a comma-separated GROUP BY expression list.
     * Each item is a (qualified) plain, ANSI-quoted, or backtick-quoted identifier.
     * E.g. '"order"', '`name`, id'.
     *
     * Both ANSI double-quotes and MySQL backticks are accepted because this
     * validator is dialect-agnostic.  Use `$db->quote()` to produce the
     * dialect-correct quoted form.
     */
    const GROUP_BY_REGEX = '/^(?:[a-zA-Z_][a-zA-Z0-9_]*|"(?:[^"]|"")+"|`(?:[^`]|``)+`)(?:\.(?:[a-zA-Z_][a-zA-Z0-9_]*|"(?:[^"]|"")+"|`(?:[^`]|``)+`))?(?:\s*,\s*(?:[a-zA-Z_][a-zA-Z0-9_]*|"(?:[^"]|"")+"|`(?:[^`]|``)+`)(?:\.(?:[a-zA-Z_][a-zA-Z0-9_]*|"(?:[^"]|"")+"|`(?:[^`]|``)+`))?)*$/';

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
     * Asserts that $name is a plain or single-segment quoted SQL column identifier.
     * Accepts plain identifiers (e.g. 'email') and ANSI/backtick-quoted identifiers
     * whose inner content is a plain identifier (e.g. '"order"', '`from`').
     * Schema-qualified names (e.g. 'public.email') are not accepted.
     *
     * @param string $name    The value to validate.
     * @param string $context Human-readable label used in the exception message.
     * @throws InvalidArgumentException If $name is not a valid plain or quoted column identifier.
     */
    public static function assertColumnIdentifier($name, $context = 'column identifier')
    {
        if (!is_string($name) || !preg_match(self::COLUMN_IDENTIFIER_REGEX, $name)) {
            throw new InvalidArgumentException(
                "Invalid {$context}: must be a plain identifier (e.g. 'email') or a quoted identifier"
                . " whose content is a valid name (e.g. '\"order\"' or '`from`')."
                . " Schema-qualified names are not allowed here."
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
                . " qualified name (e.g. 'u.email'); each segment must start with a letter or"
                . " underscore and contain only letters, digits and underscores, with one optional dot separating the segments."
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
                "Invalid table name: each name segment must start with a letter or underscore"
                . " and contain only alphanumeric characters and underscores"
                . " (optionally schema-qualified, e.g. 'schema.table')."
            );
        }
    }

    /**
     * Asserts that $expr is a valid table expression with an optional alias.
     * Accepts: 'users', 'users u', 'users AS u', 'public.users AS u'.
     *
     * @param string $expr The table expression to validate.
     * @throws InvalidArgumentException If $expr is not a valid aliased table expression.
     */
    public static function assertAlias($expr)
    {
        if (!is_string($expr) || !preg_match(self::ALIAS_IDENTIFIER_REGEX, $expr)) {
            throw new InvalidArgumentException(
                "Invalid table expression: expected a table name with an optional alias,"
                . " e.g. 'users', 'users u', or 'public.orders AS o'."
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
