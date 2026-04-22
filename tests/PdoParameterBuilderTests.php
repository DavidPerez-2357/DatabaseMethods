<?php

/**
 * tests/PdoParameterBuilderTests.php
 *
 * Unit tests for the PdoParameterBuilder utility class.
 *
 * Covers:
 *   - buildEquality()           - basic equality, prefixed placeholders, NULL (IS NULL), empty input,
 *                                 invalid identifiers, mixed NULL/non-NULL conditions
 *   - buildValues()             - indexed params, prefixed placeholders, empty input, non-sequential keys
 *   - buildNamedParams()        - named parameter array generation
 *   - normalizeNamedBindings()  - normalize/validate named placeholders
 *   - normalizeNamedWhereBindings() / resolveWhereBindings() - WHERE binding normalization
 *   - buildSetClause()          - SQL SET clause generation
 *   - buildInsertPlaceholders() - INSERT placeholder generation
 *   - buildInsertParams()       - INSERT parameter array generation
 *
 * Run via: php tests/run.php
 *
 * @author DavidPerez-2357
 * @link   https://github.com/DavidPerez-2357/DatabaseMethods
 */
class PdoParameterBuilderTests
{
    // =========================================================================
    // buildEquality - SQL fragment + params
    // =========================================================================

    public function testBuildEqualityBasic()
    {
        list($sql, $params) = PdoParameterBuilder::buildEquality(
            array('id' => 5, 'status' => 'active')
        );

        assert_equals('id = :id AND status = :status', $sql);
        assert_equals(array(':id' => 5, ':status' => 'active'), $params);
    }

    public function testBuildEqualityWithPrefix()
    {
        list($sql, $params) = PdoParameterBuilder::buildEquality(
            array('id' => 5, 'email' => 'john@email.com'),
            'where_'
        );

        assert_equals('id = :where_id AND email = :where_email', $sql);
        assert_equals(array(':where_id' => 5, ':where_email' => 'john@email.com'), $params);
    }

    public function testBuildEqualityNullValueGeneratesIsNull()
    {
        list($sql, $params) = PdoParameterBuilder::buildEquality(array('deleted_at' => null));

        assert_equals('deleted_at IS NULL', $sql);
        assert_equals(array(), $params);
    }

    public function testBuildEqualityMixedNullAndNonNull()
    {
        list($sql, $params) = PdoParameterBuilder::buildEquality(
            array('name' => 'Bob', 'deleted_at' => null, 'age' => 30)
        );

        assert_equals('name = :name AND deleted_at IS NULL AND age = :age', $sql);
        assert_equals(array(':name' => 'Bob', ':age' => 30), $params);
    }

    public function testBuildEqualityEmptyConditionsReturnsEmptyStringAndEmptyParams()
    {
        list($sql, $params) = PdoParameterBuilder::buildEquality(array());

        assert_equals('', $sql);
        assert_equals(array(), $params);
    }

    public function testBuildEqualityInvalidColumnThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildEquality(array('bad-column' => 1));
        });
    }

    public function testBuildEqualityInvalidPrefixThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildEquality(array('id' => 1), '123bad');
        });
    }

    public function testBuildEqualityPlaceholderCollisionThrows()
    {
        // 'u_a.b' → 'u_a_b' and 'u.a_b' → 'u_a_b': both produce the same placeholder name
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildEquality(array('u_a.b' => 1, 'u.a_b' => 2));
        });
    }

    public function testBuildEqualityQualifiedColumn()
    {
        list($sql, $params) = PdoParameterBuilder::buildEquality(array('u.id' => 5, 'u.deleted_at' => null));

        assert_equals('u.id = :u_id AND u.deleted_at IS NULL', $sql);
        assert_equals(array(':u_id' => 5), $params);
    }

    // =========================================================================
    // buildValues - indexed params
    // =========================================================================

    public function testBuildValuesBasic()
    {
        $params = PdoParameterBuilder::buildValues(array(1, 2, 3), 'ids_');

        assert_equals(array(':ids_0' => 1, ':ids_1' => 2, ':ids_2' => 3), $params);
    }

    public function testBuildValuesUnderscorePrefix()
    {
        $params = PdoParameterBuilder::buildValues(array('a', 'b'), '_');

        assert_equals(array(':_0' => 'a', ':_1' => 'b'), $params);
    }

    public function testBuildValuesEmptyPrefixWithValuesThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildValues(array(1, 2, 3));
        });
    }

    public function testBuildValuesInvalidPrefixThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildValues(array(1, 2), '9bad_');
        });
    }

    public function testBuildValuesEmptyArrayReturnsEmptyArray()
    {
        $params = PdoParameterBuilder::buildValues(array());

        assert_equals(array(), $params);
    }

    public function testBuildValuesNonSequentialKeysAreReindexed()
    {
        // Keys 5, 10, 15 should be re-indexed to 0, 1, 2
        $params = PdoParameterBuilder::buildValues(array(5 => 'x', 10 => 'y', 15 => 'z'), 'p_');

        assert_equals(array(':p_0' => 'x', ':p_1' => 'y', ':p_2' => 'z'), $params);
    }

    // =========================================================================
    // buildNamedParams - associative col => value map to named PDO params
    // =========================================================================

    public function testBuildNamedParamsBasic()
    {
        $params = PdoParameterBuilder::buildNamedParams(array('name' => 'Alice', 'age' => 30));

        assert_equals(array(':name' => 'Alice', ':age' => 30), $params);
    }

    public function testBuildNamedParamsWithPrefix()
    {
        $params = PdoParameterBuilder::buildNamedParams(array('name' => 'Bob'), 'set_');

        assert_equals(array(':set_name' => 'Bob'), $params);
    }

    public function testBuildNamedParamsEmptyReturnsEmptyArray()
    {
        $params = PdoParameterBuilder::buildNamedParams(array());

        assert_equals(array(), $params);
    }

    public function testBuildNamedParamsInvalidColumnThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildNamedParams(array('bad-col' => 1));
        });
    }

    public function testBuildNamedParamsInvalidPrefixThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildNamedParams(array('name' => 'Alice'), '1bad_');
        });
    }

    public function testBuildNamedParamsPlaceholderCollisionThrows()
    {
        // 'a.b' → 'a_b' and 'a_b' → 'a_b': same placeholder name
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildNamedParams(array('a.b' => 1, 'a_b' => 2));
        });
    }

    public function testBuildNamedParamsQualifiedColumn()
    {
        $params = PdoParameterBuilder::buildNamedParams(array('u.name' => 'Alice', 'u.age' => 30));

        assert_equals(array(':u_name' => 'Alice', ':u_age' => 30), $params);
    }

    // =========================================================================
    // normalizeNamedBindings - generic named placeholder normalization/validation
    // =========================================================================

    public function testNormalizeNamedBindingsAcceptsWithAndWithoutColon()
    {
        $params = PdoParameterBuilder::normalizeNamedBindings(array('id' => 10, ':name' => 'Alice'));

        assert_equals(array(':id' => 10, ':name' => 'Alice'), $params);
    }

    public function testNormalizeNamedBindingsRejectsNonStringKey()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::normalizeNamedBindings(array(0 => 'x'));
        });
    }

    public function testNormalizeNamedBindingsRejectsDoubleLeadingColon()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::normalizeNamedBindings(array('::id' => 1));
        });
    }

    public function testNormalizeNamedBindingsRejectsDuplicateAfterNormalization()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::normalizeNamedBindings(array('id' => 1, ':id' => 2));
        });
    }

    // =========================================================================
    // normalizeNamedWhereBindings / resolveWhereBindings
    // =========================================================================

    public function testNormalizeNamedWhereBindingsDetectsSetWhereConflict()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::normalizeNamedWhereBindings(
                array('id' => 10),
                array(':id' => 20)
            );
        });
    }

    public function testResolveWhereBindingsNamedKeysAreNormalized()
    {
        $params = PdoParameterBuilder::resolveWhereBindings(array('id' => 10, ':status' => 'active'));

        assert_equals(array(':id' => 10, ':status' => 'active'), $params);
    }

    public function testResolveWhereBindingsPositionalKeysAreReindexed()
    {
        $params = PdoParameterBuilder::resolveWhereBindings(array(3 => 'a', 9 => 'b'));

        assert_equals(array('a', 'b'), $params);
    }

    // =========================================================================
    // buildSetClause - SQL SET fragment from field list
    // =========================================================================

    public function testBuildSetClauseBasic()
    {
        $sql = PdoParameterBuilder::buildSetClause(array('name', 'email'));

        assert_equals('name = :name, email = :email', $sql);
    }

    public function testBuildSetClauseEmptyFieldsThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildSetClause(array());
        });
    }

    public function testBuildSetClauseInvalidFieldThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildSetClause(array('bad.col'));
        });
    }

    // =========================================================================
    // buildInsertPlaceholders - VALUES row-group strings
    // =========================================================================

    public function testBuildInsertPlaceholdersSingleRowMultipleFields()
    {
        $groups = PdoParameterBuilder::buildInsertPlaceholders(array('name', 'email'), 1);

        assert_equals(array('(:name_0, :email_0)'), $groups);
    }

    public function testBuildInsertPlaceholdersMultipleRows()
    {
        $groups = PdoParameterBuilder::buildInsertPlaceholders(array('name', 'email'), 2);

        assert_equals(
            array('(:name_0, :email_0)', '(:name_1, :email_1)'),
            $groups
        );
    }

    public function testBuildInsertPlaceholdersEmptyFieldsThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildInsertPlaceholders(array(), 1);
        });
    }

    public function testBuildInsertPlaceholdersZeroRowCountThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildInsertPlaceholders(array('col'), 0);
        });
    }

    public function testBuildInsertPlaceholdersInvalidFieldThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildInsertPlaceholders(array('bad.col'), 1);
        });
    }

    // =========================================================================
    // buildInsertParams - flat PDO param map from array of rows
    // =========================================================================

    public function testBuildInsertParamsSingleRow()
    {
        $params = PdoParameterBuilder::buildInsertParams(array(
            array('name' => 'Alice', 'age' => 30)
        ));

        assert_equals(array(':name_0' => 'Alice', ':age_0' => 30), $params);
    }

    public function testBuildInsertParamsMultipleRows()
    {
        $params = PdoParameterBuilder::buildInsertParams(array(
            array('name' => 'Alice', 'age' => 30),
            array('name' => 'Bob',   'age' => 25),
        ));

        assert_equals(
            array(':name_0' => 'Alice', ':age_0' => 30, ':name_1' => 'Bob', ':age_1' => 25),
            $params
        );
    }

    public function testBuildInsertParamsNullValueIncluded()
    {
        $params = PdoParameterBuilder::buildInsertParams(array(array('deleted_at' => null)));

        assert_true(array_key_exists(':deleted_at_0', $params));
        assert_equals(null, $params[':deleted_at_0']);
    }

    public function testBuildInsertParamsEmptyRowsThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildInsertParams(array());
        });
    }

    public function testBuildInsertParamsInvalidColumnThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildInsertParams(array(array('bad.col' => 1)));
        });
    }

    public function testBuildInsertParamsRowNotArrayThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildInsertParams(array('not-an-array'));
        });
    }

    public function testBuildInsertParamsEmptyFirstRowThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildInsertParams(array(array()));
        });
    }

    public function testBuildInsertParamsMismatchedRowKeysThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildInsertParams(array(
                array('name' => 'Alice', 'age' => 30),
                array('name' => 'Bob', 'extra' => 99),
            ));
        });
    }

    public function testBuildInsertParamsDifferentKeyOrderSucceeds()
    {
        // Rows with the same key set but different insertion order must succeed.
        // Values are mapped by the first row's field order.
        $params = PdoParameterBuilder::buildInsertParams(array(
            array('name' => 'Alice', 'age' => 30),
            array('age' => 25, 'name' => 'Bob'),
        ));

        assert_equals(array(
            ':name_0' => 'Alice',
            ':age_0'  => 30,
            ':name_1' => 'Bob',
            ':age_1'  => 25,
        ), $params);
    }
}
