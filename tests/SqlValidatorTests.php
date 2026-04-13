<?php

/**
 * tests/SqlValidatorTests.php
 *
 * Unit tests for the SqlValidator utility class.
 *
 * Covers:
 *   - IDENTIFIER_REGEX constant
 *   - QUALIFIED_IDENTIFIER_REGEX constant
 *   - ALIAS_IDENTIFIER_REGEX constant
 *   - ORDER_BY_REGEX constant
 *   - GROUP_BY_REGEX constant
 *   - assertIdentifier()        - valid, invalid, type errors
 *   - assertQualifiedIdentifier() - plain, qualified, invalid, type errors
 *   - assertTable()             - plain, schema-qualified, invalid, type errors
 *   - assertField()             - plain identifiers only, no dots, type errors
 *   - assertOrderBy()           - valid patterns, ASC/DESC, multi-column, trimming, injection, type errors
 *   - assertGroupBy()           - valid patterns, multi-column, trimming, no ASC/DESC, injection, type errors
 *
 * Run via: php tests/run.php
 *
 * @author DavidPerez-2357
 * @link   https://github.com/DavidPerez-2357/DatabaseMethods
 */
class SqlValidatorTests
{
    // =========================================================================
    // Regex constants
    // =========================================================================

    public function testIdentifierRegexMatchesPlainIdentifier()
    {
        assert_true((bool) preg_match(SqlValidator::IDENTIFIER_REGEX, 'users'));
        assert_true((bool) preg_match(SqlValidator::IDENTIFIER_REGEX, '_temp'));
        assert_true((bool) preg_match(SqlValidator::IDENTIFIER_REGEX, 'created_at'));
        assert_true((bool) preg_match(SqlValidator::IDENTIFIER_REGEX, 'Col123'));
    }

    public function testIdentifierRegexRejectsQualifiedAndInvalid()
    {
        assert_true(!preg_match(SqlValidator::IDENTIFIER_REGEX, 'users.id'));
        assert_true(!preg_match(SqlValidator::IDENTIFIER_REGEX, '1name'));
        assert_true(!preg_match(SqlValidator::IDENTIFIER_REGEX, ''));
        assert_true(!preg_match(SqlValidator::IDENTIFIER_REGEX, 'name space'));
    }

    public function testQualifiedIdentifierRegexMatchesPlainAndQualified()
    {
        assert_true((bool) preg_match(SqlValidator::QUALIFIED_IDENTIFIER_REGEX, 'users'));
        assert_true((bool) preg_match(SqlValidator::QUALIFIED_IDENTIFIER_REGEX, 'public.users'));
        assert_true((bool) preg_match(SqlValidator::QUALIFIED_IDENTIFIER_REGEX, 'dbo.orders'));
        assert_true((bool) preg_match(SqlValidator::QUALIFIED_IDENTIFIER_REGEX, 'users.email'));
    }

    public function testQualifiedIdentifierRegexRejectsInvalid()
    {
        assert_true(!preg_match(SqlValidator::QUALIFIED_IDENTIFIER_REGEX, 'a.b.c'));
        assert_true(!preg_match(SqlValidator::QUALIFIED_IDENTIFIER_REGEX, '1name'));
        assert_true(!preg_match(SqlValidator::QUALIFIED_IDENTIFIER_REGEX, ''));
        assert_true(!preg_match(SqlValidator::QUALIFIED_IDENTIFIER_REGEX, 'name-col'));
    }

    public function testAliasIdentifierRegexMatchesPlainAndAlias()
    {
        assert_true((bool) preg_match(SqlValidator::ALIAS_IDENTIFIER_REGEX, 'users'));
        assert_true((bool) preg_match(SqlValidator::ALIAS_IDENTIFIER_REGEX, 'users u'));
        assert_true((bool) preg_match(SqlValidator::ALIAS_IDENTIFIER_REGEX, 'users AS u'));
        assert_true((bool) preg_match(SqlValidator::ALIAS_IDENTIFIER_REGEX, 'public.users u'));
        assert_true((bool) preg_match(SqlValidator::ALIAS_IDENTIFIER_REGEX, 'orders AS o'));
    }

    public function testAliasIdentifierRegexRejectsInvalid()
    {
        assert_true(!preg_match(SqlValidator::ALIAS_IDENTIFIER_REGEX, '1name'));
        assert_true(!preg_match(SqlValidator::ALIAS_IDENTIFIER_REGEX, ''));
        assert_true(!preg_match(SqlValidator::ALIAS_IDENTIFIER_REGEX, 'users; DROP TABLE'));
    }

    public function testOrderByRegexMatchesValidExpressions()
    {
        assert_true((bool) preg_match(SqlValidator::ORDER_BY_REGEX, 'name'));
        assert_true((bool) preg_match(SqlValidator::ORDER_BY_REGEX, 'name ASC'));
        assert_true((bool) preg_match(SqlValidator::ORDER_BY_REGEX, 'created_at DESC'));
        assert_true((bool) preg_match(SqlValidator::ORDER_BY_REGEX, 'users.name ASC'));
        assert_true((bool) preg_match(SqlValidator::ORDER_BY_REGEX, 'name ASC, email DESC'));
    }

    public function testOrderByRegexRejectsInvalid()
    {
        assert_true(!preg_match(SqlValidator::ORDER_BY_REGEX, ''));
        assert_true(!preg_match(SqlValidator::ORDER_BY_REGEX, 'name; DROP TABLE'));
        assert_true(!preg_match(SqlValidator::ORDER_BY_REGEX, '1name'));
        assert_true(!preg_match(SqlValidator::ORDER_BY_REGEX, 'name,'));
    }

    public function testGroupByRegexMatchesValidExpressions()
    {
        assert_true((bool) preg_match(SqlValidator::GROUP_BY_REGEX, 'name'));
        assert_true((bool) preg_match(SqlValidator::GROUP_BY_REGEX, 'users.id'));
        assert_true((bool) preg_match(SqlValidator::GROUP_BY_REGEX, 'name, email'));
    }

    public function testGroupByRegexRejectsInvalid()
    {
        assert_true(!preg_match(SqlValidator::GROUP_BY_REGEX, ''));
        assert_true(!preg_match(SqlValidator::GROUP_BY_REGEX, 'name; DROP TABLE'));
        assert_true(!preg_match(SqlValidator::GROUP_BY_REGEX, '1name'));
    }

    // =========================================================================
    // assertIdentifier
    // =========================================================================

    public function testAssertIdentifierAcceptsPlainIdentifier()
    {
        SqlValidator::assertIdentifier('users');
        SqlValidator::assertIdentifier('created_at');
        SqlValidator::assertIdentifier('_tmp');
        SqlValidator::assertIdentifier('Col123');
        assert_true(true);
    }

    public function testAssertIdentifierRejectsQualifiedIdentifier()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertIdentifier('users.email');
        });
    }

    public function testAssertIdentifierRejectsDigitStart()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertIdentifier('1name');
        });
    }

    public function testAssertIdentifierRejectsEmpty()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertIdentifier('');
        });
    }

    public function testAssertIdentifierRejectsNonString()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertIdentifier(123);
        });
    }

    public function testAssertIdentifierRejectsSpaces()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertIdentifier('my table');
        });
    }

    public function testAssertIdentifierUsesContext()
    {
        $caught = null;
        try {
            SqlValidator::assertIdentifier('bad name', 'SET field');
        } catch (InvalidArgumentException $e) {
            $caught = $e;
        }
        assert_true($caught !== null);
        assert_contains('SET field', $caught->getMessage());
    }

    // =========================================================================
    // assertQualifiedIdentifier
    // =========================================================================

    public function testAssertQualifiedIdentifierAcceptsPlain()
    {
        SqlValidator::assertQualifiedIdentifier('users');
        SqlValidator::assertQualifiedIdentifier('created_at');
        assert_true(true);
    }

    public function testAssertQualifiedIdentifierAcceptsQualified()
    {
        SqlValidator::assertQualifiedIdentifier('public.users');
        SqlValidator::assertQualifiedIdentifier('users.email');
        assert_true(true);
    }

    public function testAssertQualifiedIdentifierRejectsTwoDots()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertQualifiedIdentifier('a.b.c');
        });
    }

    public function testAssertQualifiedIdentifierRejectsEmpty()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertQualifiedIdentifier('');
        });
    }

    public function testAssertQualifiedIdentifierRejectsNonString()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertQualifiedIdentifier(null);
        });
    }

    public function testAssertQualifiedIdentifierUsesContext()
    {
        $caught = null;
        try {
            SqlValidator::assertQualifiedIdentifier('bad!name', 'condition column');
        } catch (InvalidArgumentException $e) {
            $caught = $e;
        }
        assert_true($caught !== null);
        assert_contains('condition column', $caught->getMessage());
    }

    // =========================================================================
    // assertTable
    // =========================================================================

    public function testAssertTableAcceptsPlainTable()
    {
        SqlValidator::assertTable('users');
        SqlValidator::assertTable('order_items');
        assert_true(true);
    }

    public function testAssertTableAcceptsSchemaQualified()
    {
        SqlValidator::assertTable('public.users');
        SqlValidator::assertTable('dbo.orders');
        assert_true(true);
    }

    public function testAssertTableRejectsEmpty()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertTable('');
        });
    }

    public function testAssertTableRejectsSpaces()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertTable('my table');
        });
    }

    public function testAssertTableRejectsSqlInjection()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertTable('users; DROP TABLE users');
        });
    }

    public function testAssertTableRejectsNonString()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertTable(42);
        });
    }

    // =========================================================================
    // assertField
    // =========================================================================

    public function testAssertFieldAcceptsPlainField()
    {
        SqlValidator::assertField('name');
        SqlValidator::assertField('created_at');
        SqlValidator::assertField('_col');
        assert_true(true);
    }

    public function testAssertFieldRejectsQualified()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertField('users.name');
        });
    }

    public function testAssertFieldRejectsEmpty()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertField('');
        });
    }

    public function testAssertFieldRejectsNonString()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertField(array('name'));
        });
    }

    public function testAssertFieldRejectsDigitStart()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertField('1col');
        });
    }

    // =========================================================================
    // assertOrderBy
    // =========================================================================

    public function testAssertOrderByAcceptsSimpleColumn()
    {
        assert_equals('name', SqlValidator::assertOrderBy('name'));
    }

    public function testAssertOrderByAcceptsColumnWithAsc()
    {
        assert_equals('name ASC', SqlValidator::assertOrderBy('name ASC'));
    }

    public function testAssertOrderByAcceptsColumnWithDesc()
    {
        assert_equals('created_at DESC', SqlValidator::assertOrderBy('created_at DESC'));
    }

    public function testAssertOrderByAcceptsMultipleColumns()
    {
        assert_equals('name ASC, id DESC', SqlValidator::assertOrderBy('name ASC, id DESC'));
    }

    public function testAssertOrderByAcceptsQualifiedColumn()
    {
        assert_equals('users.name', SqlValidator::assertOrderBy('users.name'));
    }

    public function testAssertOrderByAcceptsQualifiedWithDirection()
    {
        assert_equals('users.created_at DESC', SqlValidator::assertOrderBy('users.created_at DESC'));
    }

    public function testAssertOrderByTrimsWhitespace()
    {
        assert_equals('name ASC', SqlValidator::assertOrderBy('  name ASC  '));
    }

    public function testAssertOrderByAcceptsCaseInsensitiveDirection()
    {
        assert_equals('name asc', SqlValidator::assertOrderBy('name asc'));
    }

    public function testAssertOrderByRejectsEmpty()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertOrderBy('');
        });
    }

    public function testAssertOrderByRejectsWhitespaceOnly()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertOrderBy('   ');
        });
    }

    public function testAssertOrderByRejectsSqlInjection()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertOrderBy('name; DROP TABLE users');
        });
    }

    public function testAssertOrderByRejectsUnionInjection()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertOrderBy('name UNION SELECT password FROM users');
        });
    }

    public function testAssertOrderByRejectsDigitStart()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertOrderBy('1name');
        });
    }

    public function testAssertOrderByRejectsTrailingComma()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertOrderBy('name,');
        });
    }

    public function testAssertOrderByRejectsNonStringInteger()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertOrderBy(123);
        });
    }

    public function testAssertOrderByRejectsNonStringArray()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertOrderBy(array('name'));
        });
    }

    // =========================================================================
    // assertGroupBy
    // =========================================================================

    public function testAssertGroupByAcceptsSimpleColumn()
    {
        assert_equals('name', SqlValidator::assertGroupBy('name'));
    }

    public function testAssertGroupByAcceptsQualifiedColumn()
    {
        assert_equals('users.id', SqlValidator::assertGroupBy('users.id'));
    }

    public function testAssertGroupByAcceptsMultipleColumns()
    {
        assert_equals('name, email', SqlValidator::assertGroupBy('name, email'));
    }

    public function testAssertGroupByTrimsWhitespace()
    {
        assert_equals('name', SqlValidator::assertGroupBy('  name  '));
    }

    public function testAssertGroupByRejectsEmpty()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertGroupBy('');
        });
    }

    public function testAssertGroupByRejectsWhitespaceOnly()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertGroupBy('   ');
        });
    }

    public function testAssertGroupByRejectsSqlInjection()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertGroupBy('name; DROP TABLE users');
        });
    }

    public function testAssertGroupByRejectsAscDesc()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertGroupBy('name ASC');
        });
    }

    public function testAssertGroupByRejectsDigitStart()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertGroupBy('1name');
        });
    }

    public function testAssertGroupByRejectsNonStringInteger()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertGroupBy(123);
        });
    }

    public function testAssertGroupByRejectsNonStringArray()
    {
        assert_throws('InvalidArgumentException', function () {
            SqlValidator::assertGroupBy(array('name'));
        });
    }
}
