<?php

/**
 * tests/SqlValidatorTests.php
 *
 * Unit tests for the SqlValidator utility class.
 *
 * Covers:
 *   - assertIdentifier()          - valid plain identifiers, invalid input, context label
 *   - assertQualifiedIdentifier() - plain and schema-qualified identifiers, invalid input
 *   - assertTable()               - plain and schema-qualified table names, invalid input
 *   - assertAlias()               - table with optional alias expressions, invalid input
 *   - assertField()               - plain field names only (no dots), invalid input
 *   - assertOrderBy()             - valid expressions, whitespace trimming, invalid input
 *   - assertGroupBy()             - valid expressions, whitespace trimming, invalid input
 *
 * Run via: php tests/run.php
 *
 * @author DavidPerez-2357
 * @link   https://github.com/DavidPerez-2357/DatabaseMethods
 */
class SqlValidatorTests
{
    // =========================================================================
    // assertIdentifier
    // =========================================================================

    public function testAssertIdentifierAcceptsValidIdentifiers()
    {
        SqlValidator::assertIdentifier('users');
        SqlValidator::assertIdentifier('created_at');
        SqlValidator::assertIdentifier('_tmp');
        assert_true(true);
    }

    public function testAssertIdentifierRejectsInvalid()
    {
        foreach (array('users.email', '1name', '', 'my table', 123) as $bad) {
            assert_throws('InvalidArgumentException', function () use ($bad) {
                SqlValidator::assertIdentifier($bad);
            });
        }
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

    public function testAssertQualifiedIdentifierAcceptsPlainAndQualified()
    {
        SqlValidator::assertQualifiedIdentifier('users');
        SqlValidator::assertQualifiedIdentifier('public.users');
        SqlValidator::assertQualifiedIdentifier('users.email');
        assert_true(true);
    }

    public function testAssertQualifiedIdentifierAcceptsAnsiQuoted()
    {
        SqlValidator::assertQualifiedIdentifier('"order"');
        SqlValidator::assertQualifiedIdentifier('"public"."order"');
        SqlValidator::assertQualifiedIdentifier('"say ""hi"""');
        assert_true(true);
    }

    public function testAssertQualifiedIdentifierAcceptsBacktickQuoted()
    {
        SqlValidator::assertQualifiedIdentifier('`order`');
        SqlValidator::assertQualifiedIdentifier('`public`.`order`');
        assert_true(true);
    }

    public function testAssertQualifiedIdentifierRejectsInvalid()
    {
        foreach (array('a.b.c', '1name', '', 'name-col', null) as $bad) {
            assert_throws('InvalidArgumentException', function () use ($bad) {
                SqlValidator::assertQualifiedIdentifier($bad);
            });
        }
    }

    // =========================================================================
    // assertTable
    // =========================================================================

    public function testAssertTableAcceptsPlainAndSchemaQualified()
    {
        SqlValidator::assertTable('users');
        SqlValidator::assertTable('public.users');
        SqlValidator::assertTable('dbo.orders');
        assert_true(true);
    }

    public function testAssertTableAcceptsAnsiQuoted()
    {
        SqlValidator::assertTable('"order"');
        SqlValidator::assertTable('"public"."order"');
        assert_true(true);
    }

    public function testAssertTableAcceptsBacktickQuoted()
    {
        SqlValidator::assertTable('`order`');
        SqlValidator::assertTable('`public`.`order`');
        assert_true(true);
    }

    public function testAssertTableRejectsInvalid()
    {
        foreach (array('', 'my table', 'users; DROP TABLE users', 42) as $bad) {
            assert_throws('InvalidArgumentException', function () use ($bad) {
                SqlValidator::assertTable($bad);
            });
        }
    }

    // =========================================================================
    // assertAlias
    // =========================================================================

    public function testAssertAliasAcceptsValidExpressions()
    {
        SqlValidator::assertAlias('users');
        SqlValidator::assertAlias('users u');
        SqlValidator::assertAlias('users AS u');
        SqlValidator::assertAlias('public.orders AS o');
        assert_true(true);
    }

    public function testAssertAliasAcceptsAnsiQuotedWithOptionalAlias()
    {
        SqlValidator::assertAlias('"order"');
        SqlValidator::assertAlias('"order" o');
        SqlValidator::assertAlias('"order" AS o');
        SqlValidator::assertAlias('"public"."order" AS o');
        assert_true(true);
    }

    public function testAssertAliasAcceptsBacktickQuotedWithOptionalAlias()
    {
        SqlValidator::assertAlias('`order`');
        SqlValidator::assertAlias('`order` o');
        SqlValidator::assertAlias('`order` AS o');
        assert_true(true);
    }

    public function testAssertAliasRejectsInvalid()
    {
        foreach (array('', '1name', 'users; DROP TABLE', 99) as $bad) {
            assert_throws('InvalidArgumentException', function () use ($bad) {
                SqlValidator::assertAlias($bad);
            });
        }
    }

    // =========================================================================
    // assertField
    // =========================================================================

    public function testAssertFieldAcceptsPlainField()
    {
        SqlValidator::assertField('name');
        SqlValidator::assertField('created_at');
        assert_true(true);
    }

    public function testAssertFieldRejectsInvalid()
    {
        foreach (array('users.name', '', '1col', array('name')) as $bad) {
            assert_throws('InvalidArgumentException', function () use ($bad) {
                SqlValidator::assertField($bad);
            });
        }
    }

    // =========================================================================
    // assertOrderBy
    // =========================================================================

    public function testAssertOrderByAcceptsValidExpressions()
    {
        assert_equals('name', SqlValidator::assertOrderBy('name'));
        assert_equals('created_at DESC', SqlValidator::assertOrderBy('created_at DESC'));
        assert_equals('users.name ASC', SqlValidator::assertOrderBy('users.name ASC'));
        assert_equals('name ASC, id DESC', SqlValidator::assertOrderBy('name ASC, id DESC'));
    }

    public function testAssertOrderByAcceptsAnsiQuoted()
    {
        assert_equals('"order" ASC', SqlValidator::assertOrderBy('"order" ASC'));
        assert_equals('"users"."order" DESC', SqlValidator::assertOrderBy('"users"."order" DESC'));
    }

    public function testAssertOrderByAcceptsBacktickQuoted()
    {
        assert_equals('`order` ASC', SqlValidator::assertOrderBy('`order` ASC'));
    }

    public function testAssertOrderByTrimsWhitespace()
    {
        assert_equals('name ASC', SqlValidator::assertOrderBy('  name ASC  '));
    }

    public function testAssertOrderByRejectsInvalid()
    {
        foreach (array('', '   ', 'name; DROP TABLE users', '1name', 'name,', 123) as $bad) {
            assert_throws('InvalidArgumentException', function () use ($bad) {
                SqlValidator::assertOrderBy($bad);
            });
        }
    }

    // =========================================================================
    // assertGroupBy
    // =========================================================================

    public function testAssertGroupByAcceptsValidExpressions()
    {
        assert_equals('name', SqlValidator::assertGroupBy('name'));
        assert_equals('users.id', SqlValidator::assertGroupBy('users.id'));
        assert_equals('name, email', SqlValidator::assertGroupBy('name, email'));
    }

    public function testAssertGroupByAcceptsAnsiQuoted()
    {
        assert_equals('"order"', SqlValidator::assertGroupBy('"order"'));
        assert_equals('"users"."order"', SqlValidator::assertGroupBy('"users"."order"'));
    }

    public function testAssertGroupByAcceptsBacktickQuoted()
    {
        assert_equals('`order`', SqlValidator::assertGroupBy('`order`'));
    }

    public function testAssertGroupByTrimsWhitespace()
    {
        assert_equals('name', SqlValidator::assertGroupBy('  name  '));
    }

    public function testAssertGroupByRejectsInvalid()
    {
        foreach (array('', '   ', 'name; DROP TABLE users', 'name ASC', '1name', 123) as $bad) {
            assert_throws('InvalidArgumentException', function () use ($bad) {
                SqlValidator::assertGroupBy($bad);
            });
        }
    }
}
