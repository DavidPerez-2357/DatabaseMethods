<?php

/**
 * tests/PdoParameterBuilderTests.php
 *
 * Unit tests for the PdoParameterBuilder utility class.
 *
 * Covers:
 *   - buildEquality() — basic equality, prefixed placeholders, NULL (IS NULL), empty input,
 *     invalid identifiers, mixed NULL/non-NULL conditions
 *   - buildValues()   — indexed params, prefixed placeholders, empty input, non-sequential keys
 *
 * Run via: php tests/run.php
 *
 * @author DavidPerez-2357
 * @link   https://github.com/DavidPerez-2357/DatabaseMethods
 */
class PdoParameterBuilderTests
{
    // =========================================================================
    // buildEquality — SQL fragment + params
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

    public function testBuildEqualitySingleCondition()
    {
        list($sql, $params) = PdoParameterBuilder::buildEquality(array('name' => 'Alice'));

        assert_equals('name = :name', $sql);
        assert_equals(array(':name' => 'Alice'), $params);
    }

    public function testBuildEqualityNullValueGeneratesIsNull()
    {
        list($sql, $params) = PdoParameterBuilder::buildEquality(array('deleted_at' => null));

        assert_equals('deleted_at IS NULL', $sql);
        assert_equals(array(), $params);
    }

    public function testBuildEqualityNullValueExcludedFromParams()
    {
        list(, $params) = PdoParameterBuilder::buildEquality(array('deleted_at' => null));

        assert_true(!array_key_exists(':deleted_at', $params));
    }

    public function testBuildEqualityMixedNullAndNonNull()
    {
        list($sql, $params) = PdoParameterBuilder::buildEquality(
            array('name' => 'Bob', 'deleted_at' => null, 'age' => 30)
        );

        assert_equals('name = :name AND deleted_at IS NULL AND age = :age', $sql);
        assert_equals(array(':name' => 'Bob', ':age' => 30), $params);
    }

    public function testBuildEqualityNullWithPrefix()
    {
        list($sql, $params) = PdoParameterBuilder::buildEquality(
            array('archived_at' => null),
            'w_'
        );

        // NULL generates IS NULL regardless of prefix (no placeholder produced)
        assert_equals('archived_at IS NULL', $sql);
        assert_equals(array(), $params);
    }

    public function testBuildEqualityEmptyConditionsReturnsEmptyStringAndEmptyParams()
    {
        list($sql, $params) = PdoParameterBuilder::buildEquality(array());

        assert_equals('', $sql);
        assert_equals(array(), $params);
    }

    public function testBuildEqualityIntegerValue()
    {
        list($sql, $params) = PdoParameterBuilder::buildEquality(array('id' => 0));

        assert_equals('id = :id', $sql);
        assert_equals(array(':id' => 0), $params);
    }

    public function testBuildEqualityInvalidColumnThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildEquality(array('bad.column' => 1));
        });
    }

    public function testBuildEqualityColumnWithSpaceThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildEquality(array('bad column' => 1));
        });
    }

    public function testBuildEqualityColumnStartingWithDigitThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            PdoParameterBuilder::buildEquality(array('1col' => 1));
        });
    }

    public function testBuildEqualityReturnsTwoElementArray()
    {
        $result = PdoParameterBuilder::buildEquality(array('x' => 1));

        assert_true(is_array($result));
        assert_equals(2, count($result));
        assert_true(is_string($result[0]));
        assert_true(is_array($result[1]));
    }

    public function testBuildEqualityPrefixAppearedInAllPlaceholders()
    {
        list($sql, $params) = PdoParameterBuilder::buildEquality(
            array('a' => 1, 'b' => 2),
            'set_'
        );

        assert_contains(':set_a', $sql);
        assert_contains(':set_b', $sql);
        assert_true(array_key_exists(':set_a', $params));
        assert_true(array_key_exists(':set_b', $params));
    }

    // =========================================================================
    // buildValues — indexed params
    // =========================================================================

    public function testBuildValuesBasic()
    {
        $params = PdoParameterBuilder::buildValues(array(1, 2, 3), 'ids_');

        assert_equals(array(':ids_0' => 1, ':ids_1' => 2, ':ids_2' => 3), $params);
    }

    public function testBuildValuesSingleElement()
    {
        $params = PdoParameterBuilder::buildValues(array('john@email.com'), 'email_');

        assert_equals(array(':email_0' => 'john@email.com'), $params);
    }

    public function testBuildValuesEmptyPrefix()
    {
        $params = PdoParameterBuilder::buildValues(array('a', 'b'), '');

        assert_equals(array(':0' => 'a', ':1' => 'b'), $params);
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

    public function testBuildValuesStringValues()
    {
        $params = PdoParameterBuilder::buildValues(array('Alice', 'Bob', 'Charlie'), 'name_');

        assert_equals(
            array(':name_0' => 'Alice', ':name_1' => 'Bob', ':name_2' => 'Charlie'),
            $params
        );
    }

    public function testBuildValuesMixedTypes()
    {
        $params = PdoParameterBuilder::buildValues(array(1, 'two', 3.0, null), 'v_');

        assert_equals(
            array(':v_0' => 1, ':v_1' => 'two', ':v_2' => 3.0, ':v_3' => null),
            $params
        );
    }

    public function testBuildValuesNullValueIncluded()
    {
        // Unlike buildEquality, buildValues does NOT skip NULL values
        $params = PdoParameterBuilder::buildValues(array(null), 'x_');

        assert_true(array_key_exists(':x_0', $params));
        assert_equals(null, $params[':x_0']);
    }

    public function testBuildValuesCountMatchesInput()
    {
        $input  = array(10, 20, 30, 40, 50);
        $params = PdoParameterBuilder::buildValues($input, 'n_');

        assert_equals(count($input), count($params));
    }

    public function testBuildValuesKeysStartWithColon()
    {
        $params = PdoParameterBuilder::buildValues(array('a', 'b'), 'col_');

        foreach (array_keys($params) as $key) {
            assert_equals(':', $key[0]);
        }
    }

    public function testBuildValuesUsedForInsertOneRowPlaceholders()
    {
        // Simulate how Database::insertOne() builds row-0 placeholders per field.
        $fieldsToInsert = array('name' => 'Alice', 'email' => 'alice@example.com');
        $fields         = array_keys($fieldsToInsert);

        $placeholders = array();
        foreach ($fields as $field) {
            $placeholders = array_merge(
                $placeholders,
                PdoParameterBuilder::buildValues(array($fieldsToInsert[$field]), $field . '_')
            );
        }

        assert_equals(
            array(':name_0' => 'Alice', ':email_0' => 'alice@example.com'),
            $placeholders
        );
    }

    public function testBuildValuesUsedForInsertManyRowPlaceholders()
    {
        // Simulate how Database::insertMany() builds per-field multi-row placeholders.
        $rows   = array(
            array('name' => 'Alice', 'age' => 30),
            array('name' => 'Bob',   'age' => 25),
        );
        $fields = array_keys($rows[0]);

        $placeholders = array();
        foreach ($fields as $field) {
            $fieldValues = array();
            foreach ($rows as $row) {
                $fieldValues[] = $row[$field];
            }
            $placeholders = array_merge(
                $placeholders,
                PdoParameterBuilder::buildValues($fieldValues, $field . '_')
            );
        }

        assert_equals(
            array(
                ':name_0' => 'Alice',
                ':name_1' => 'Bob',
                ':age_0'  => 30,
                ':age_1'  => 25,
            ),
            $placeholders
        );
    }
}
