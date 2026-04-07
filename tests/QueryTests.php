<?php
/**
 * tests/QueryTests.php
 *
 * Comprehensive test suite for the Query class.
 *
 * Covers:
 *   - Constructor (valid array / empty / non-array)
 *   - __toString() (lazy build / warning on error / caching)
 *   - getQuery() (lazy build / exception propagation / caching)
 *   - Static factory methods: select(), insert(), update(), delete()
 *   - All chainable setters: from(), table(), fields(), where(), join(),
 *     joins(), groupBy(), having(), orderBy(), limit(), offset(), valuesCount()
 *   - Cache-invalidation: setter after build resets cached SQL
 *   - buildSelectQuery() — full SQL, all clauses, edge cases
 *   - buildPDOInsertQuery() — single/multi-row placeholders, error paths
 *   - buildPDOUpdateQuery() — SET clause, JOINs, error paths
 *   - buildDeleteQuery() — WHERE, ORDER BY, LIMIT semantics, error paths
 *   - validateOrderBy() — valid patterns, injection attempts, type errors
 *
 * Run via: php tests/run.php
 *
 * @author DavidPerez-2357
 * @link   https://github.com/DavidPerez-2357/DatabaseMethods
 */
class QueryTests
{
    // =========================================================================
    // Helper: capture E_USER_WARNING emissions without letting them propagate
    // =========================================================================

    /**
     * Calls $fn inside a custom error handler and collects any E_USER_WARNING
     * strings it emits.
     *
     * @param  callable $fn
     * @return array{result: mixed, warnings: string[]}
     */
    private function captureWarnings($fn)
    {
        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            if ($errno === E_USER_WARNING) {
                $warnings[] = $errstr;
                return true;
            }
            return false;
        });
        $result = call_user_func($fn);
        restore_error_handler();
        return ['result' => $result, 'warnings' => $warnings];
    }

    // =========================================================================
    // Constructor
    // =========================================================================

    public function testConstructorWithNonEmptyArrayBuildsImmediately()
    {
        $q = new Query([
            'method' => 'SELECT',
            'fields' => ['id'],
            'table'  => 'users',
        ]);
        assert_equals('SELECT id FROM users', $q->getQuery());
    }

    public function testConstructorWithEmptyArrayDefersBuilding()
    {
        $q = new Query([]);
        assert_true(true); // no exception = pass
    }

    public function testConstructorWithNoArgumentDefersBuilding()
    {
        $q = new Query();
        assert_true(true);
    }

    public function testConstructorWithStringThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            new Query('SELECT * FROM users');
        });
    }

    public function testConstructorWithNullThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            new Query(null);
        });
    }

    public function testConstructorWithIntegerThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            new Query(42);
        });
    }

    // =========================================================================
    // __toString
    // =========================================================================

    public function testToStringReturnsBuiltSql()
    {
        $q = Query::select(['id'])->from('users');
        assert_equals('SELECT id FROM users', (string) $q);
    }

    public function testToStringCachesSqlOnRepeatCalls()
    {
        $q = Query::select()->from('users');
        assert_equals((string) $q, (string) $q);
    }

    public function testToStringOnBuildErrorEmitsWarningAndReturnsEmptyString()
    {
        $q      = new Query(); // lazy; no method set — buildQuery() will throw
        $result = $this->captureWarnings(function () use ($q) {
            return (string) $q;
        });
        assert_equals('', $result['result'], '__toString must return empty string on build error');
        assert_equals(1, count($result['warnings']), 'Exactly one E_USER_WARNING should be emitted');
        assert_contains('Query method is required', $result['warnings'][0]);
    }

    public function testToStringWarningMessageContainsMethodName()
    {
        $q      = new Query();
        $result = $this->captureWarnings(function () use ($q) {
            return (string) $q;
        });
        assert_contains('__toString', $result['warnings'][0]);
    }

    // =========================================================================
    // getQuery
    // =========================================================================

    public function testGetQueryReturnsSqlString()
    {
        $q = Query::select(['id', 'name'])->from('users');
        assert_equals('SELECT id, name FROM users', $q->getQuery());
    }

    public function testGetQueryPropagatesException()
    {
        $q = new Query(); // no method set
        assert_throws('InvalidArgumentException', function () use ($q) {
            $q->getQuery();
        });
    }

    public function testGetQueryCachesSqlOnRepeatCalls()
    {
        $q = Query::select()->from('t');
        assert_equals($q->getQuery(), $q->getQuery());
    }

    // =========================================================================
    // Factory: select()
    // =========================================================================

    public function testSelectWithNoArgsDefaultsToWildcard()
    {
        $sql = Query::select()->from('users')->getQuery();
        assert_equals('SELECT * FROM users', $sql);
    }

    public function testSelectWithEmptyArrayDefaultsToWildcard()
    {
        $sql = Query::select([])->from('users')->getQuery();
        assert_equals('SELECT * FROM users', $sql);
    }

    public function testSelectWithNullDefaultsToWildcard()
    {
        $sql = Query::select(null)->from('users')->getQuery();
        assert_equals('SELECT * FROM users', $sql);
    }

    public function testSelectWithStringFieldNormalizesToArray()
    {
        $sql = Query::select('id')->from('users')->getQuery();
        assert_equals('SELECT id FROM users', $sql);
    }

    public function testSelectWithStringZeroIsNormalizedNotDefaulted()
    {
        // '0' is a valid (if unusual) column reference; it must NOT silently
        // become SELECT * (old empty()-based code would have done that).
        $sql = Query::select('0')->from('t')->getQuery();
        assert_equals('SELECT 0 FROM t', $sql);
    }

    public function testSelectWithArrayFields()
    {
        $sql = Query::select(['id', 'name', 'email'])->from('users')->getQuery();
        assert_equals('SELECT id, name, email FROM users', $sql);
    }

    public function testSelectWithEmptyStringThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select('');
        });
    }

    public function testSelectWithWhitespaceOnlyStringThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select('   ');
        });
    }

    public function testSelectWithIntegerThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select(123);
        });
    }

    public function testSelectWithBooleanThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select(true);
        });
    }

    // =========================================================================
    // Factory: insert()
    // =========================================================================

    public function testInsertWithTableAndFieldsArray()
    {
        $sql = Query::insert('users', ['name', 'email'])->getQuery();
        assert_equals('INSERT INTO users (name, email) VALUES (:name_0, :email_0)', $sql);
    }

    public function testInsertWithStringFieldNormalizesToArray()
    {
        $sql = Query::insert('users', 'name')->getQuery();
        assert_equals('INSERT INTO users (name) VALUES (:name_0)', $sql);
    }

    public function testInsertWithNoFieldsDefersBuildingUntilFieldsCall()
    {
        $sql = Query::insert('users')->fields(['id', 'email'])->getQuery();
        assert_equals('INSERT INTO users (id, email) VALUES (:id_0, :email_0)', $sql);
    }

    public function testInsertWithMultipleRows()
    {
        $sql      = Query::insert('users', ['name', 'email'])->valuesCount(2)->getQuery();
        $expected = 'INSERT INTO users (name, email) VALUES (:name_0, :email_0), (:name_1, :email_1)';
        assert_equals($expected, $sql);
    }

    public function testInsertWithThreeRows()
    {
        $sql = Query::insert('t', ['a'])->valuesCount(3)->getQuery();
        assert_equals('INSERT INTO t (a) VALUES (:a_0), (:a_1), (:a_2)', $sql);
    }

    public function testInsertWithInvalidFieldsTypeThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::insert('users', 42);
        });
    }

    public function testInsertMissingFieldsThrowsAtBuildTime()
    {
        $q = Query::insert('users'); // fields not yet set — lazy
        assert_throws('InvalidArgumentException', function () use ($q) {
            $q->getQuery();
        });
    }

    public function testInsertArrayConstructor()
    {
        $q = new Query([
            'method' => 'INSERT',
            'table'  => 'users',
            'fields' => ['name', 'email'],
        ]);
        assert_equals('INSERT INTO users (name, email) VALUES (:name_0, :email_0)', $q->getQuery());
    }

    public function testInsertArrayConstructorMultipleRows()
    {
        $q = new Query([
            'method'           => 'INSERT',
            'table'            => 'users',
            'fields'           => ['name'],
            'values_to_insert' => 2,
        ]);
        assert_equals('INSERT INTO users (name) VALUES (:name_0), (:name_1)', $q->getQuery());
    }

    // =========================================================================
    // Factory: update()
    // =========================================================================

    public function testUpdateWithTableAndFieldsArray()
    {
        $sql = Query::update('users', ['name', 'email'])->where('id = :id')->getQuery();
        assert_equals('UPDATE users SET name = :name, email = :email WHERE id = :id', $sql);
    }

    public function testUpdateWithStringFieldNormalizesToArray()
    {
        $sql = Query::update('users', 'name')->where('id = :id')->getQuery();
        assert_equals('UPDATE users SET name = :name WHERE id = :id', $sql);
    }

    public function testUpdateWithNoFieldsSetViaChain()
    {
        $sql = Query::update('users')->fields(['name'])->where('id = :id')->getQuery();
        assert_equals('UPDATE users SET name = :name WHERE id = :id', $sql);
    }

    public function testUpdateWithInvalidFieldsTypeThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::update('users', 42);
        });
    }

    public function testUpdateMissingFieldsThrowsAtBuildTime()
    {
        $q = Query::update('users'); // fields not yet set — lazy
        assert_throws('InvalidArgumentException', function () use ($q) {
            $q->getQuery();
        });
    }

    public function testUpdateWithoutWhereClause()
    {
        $sql = Query::update('users', ['status'])->getQuery();
        assert_equals('UPDATE users SET status = :status', $sql);
    }

    public function testUpdateArrayConstructor()
    {
        $q = new Query([
            'method' => 'UPDATE',
            'table'  => 'users',
            'fields' => ['name'],
            'where'  => 'id = :id',
        ]);
        assert_equals('UPDATE users SET name = :name WHERE id = :id', $q->getQuery());
    }

    // =========================================================================
    // Factory: delete()
    // =========================================================================

    public function testDeleteBasicFactory()
    {
        $sql = Query::delete('users')->getQuery();
        assert_equals('DELETE FROM users', $sql);
    }

    public function testDeleteWithWhereFactory()
    {
        $sql = Query::delete('users')->where('id = :id')->getQuery();
        assert_equals('DELETE FROM users WHERE id = :id', $sql);
    }

    public function testDeleteArrayConstructor()
    {
        $q = new Query(['method' => 'DELETE', 'table' => 'users', 'where' => 'id = :id']);
        assert_equals('DELETE FROM users WHERE id = :id', $q->getQuery());
    }

    // =========================================================================
    // Fluent setters: from() / table()
    // =========================================================================

    public function testFromSetsTable()
    {
        $sql = Query::select()->from('orders')->getQuery();
        assert_contains('FROM orders', $sql);
    }

    public function testTableIsAliasOfFrom()
    {
        assert_equals(
            Query::select()->from('orders')->getQuery(),
            Query::select()->table('orders')->getQuery()
        );
    }

    public function testFromOverridesPreviousTable()
    {
        $sql = Query::select()->from('users')->from('orders')->getQuery();
        assert_contains('FROM orders', $sql);
        assert_not_contains('FROM users', $sql);
    }

    public function testSelectMissingTableThrows()
    {
        $q = Query::select(['id']); // no ->from() call
        assert_throws('InvalidArgumentException', function () use ($q) {
            $q->getQuery();
        });
    }

    // =========================================================================
    // Fluent setters: fields()
    // =========================================================================

    public function testFieldsOverridesExistingFields()
    {
        $sql = Query::select(['a', 'b'])->from('t')->fields(['x', 'y'])->getQuery();
        assert_equals('SELECT x, y FROM t', $sql);
    }

    public function testFieldsWithNonArrayThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select()->fields(true);
        });
    }

    public function testFieldsWithIntegerThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select()->fields(42);
        });
    }

    // =========================================================================
    // Fluent setters: where()
    // =========================================================================

    public function testWhereAppendsClause()
    {
        $sql = Query::select()->from('t')->where('active = 1')->getQuery();
        assert_contains('WHERE active = 1', $sql);
    }

    public function testWhereOverridesPreviousWhere()
    {
        $sql = Query::select()->from('t')->where('a = 1')->where('b = 2')->getQuery();
        assert_contains('WHERE b = 2', $sql);
        assert_not_contains('WHERE a = 1', $sql);
    }

    // =========================================================================
    // Fluent setters: join() / joins()
    // =========================================================================

    public function testJoinAppendsJoinClause()
    {
        $sql = Query::select()->from('users')
            ->join('LEFT JOIN orders ON users.id = orders.user_id')
            ->getQuery();
        assert_contains('LEFT JOIN orders ON users.id = orders.user_id', $sql);
    }

    public function testJoinAppendsMultipleJoins()
    {
        $sql = Query::select()->from('users')
            ->join('LEFT JOIN orders ON users.id = orders.user_id')
            ->join('INNER JOIN roles ON users.role_id = roles.id')
            ->getQuery();
        assert_contains('LEFT JOIN orders', $sql);
        assert_contains('INNER JOIN roles', $sql);
    }

    public function testJoinWithEmptyStringThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select()->from('t')->join('');
        });
    }

    public function testJoinWithWhitespaceOnlyStringThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select()->from('t')->join('   ');
        });
    }

    public function testJoinWithIntegerThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select()->from('t')->join(42);
        });
    }

    public function testJoinWithArrayThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select()->from('t')->join(['LEFT JOIN foo ON bar']);
        });
    }

    public function testJoinsWithNullClearsAllJoins()
    {
        $sql = Query::select()->from('users')
            ->join('LEFT JOIN orders ON users.id = orders.user_id')
            ->joins(null)
            ->getQuery();
        assert_not_contains('JOIN', $sql);
    }

    public function testJoinsWithStringNormalizesToSingleJoin()
    {
        $sql = Query::select()->from('users')
            ->joins('LEFT JOIN orders ON users.id = orders.user_id')
            ->getQuery();
        assert_contains('LEFT JOIN orders', $sql);
    }

    public function testJoinsWithArrayReplacesAllJoins()
    {
        $sql = Query::select()->from('users')
            ->join('INNER JOIN old_tbl ON a = b')
            ->joins(['LEFT JOIN orders ON users.id = orders.user_id'])
            ->getQuery();
        assert_not_contains('INNER JOIN old_tbl', $sql);
        assert_contains('LEFT JOIN orders', $sql);
    }

    public function testJoinsWithIntegerThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select()->from('t')->joins(42);
        });
    }

    public function testJoinNormalizesNonArrayExistingJoins()
    {
        // Simulate array-constructor path where 'joins' is stored as a plain
        // string and then join() is called — join() must normalize it first.
        $q = Query::select(['*'])
            ->from('users')
            ->joins('LEFT JOIN orders ON users.id = orders.user_id')
            // Replace internal joins array with a raw string via joins() helper,
            // then call join() again to trigger the normalization branch.
            ->joins('LEFT JOIN orders ON users.id = orders.user_id')
            ->join('INNER JOIN roles ON users.role_id = roles.id');
        $sql = $q->getQuery();
        assert_contains('LEFT JOIN orders', $sql);
        assert_contains('INNER JOIN roles', $sql);
    }

    // =========================================================================
    // Fluent setters: groupBy() / having()
    // =========================================================================

    public function testGroupBySetsGroupByClause()
    {
        $sql = Query::select(['user_id'])->from('orders')
            ->groupBy('user_id')
            ->getQuery();
        assert_contains('GROUP BY user_id', $sql);
    }

    public function testHavingSetsHavingClause()
    {
        $sql = Query::select(['user_id'])->from('orders')
            ->groupBy('user_id')
            ->having('COUNT(*) > 1')
            ->getQuery();
        assert_contains('HAVING COUNT(*) > 1', $sql);
    }

    // =========================================================================
    // Fluent setters: orderBy()
    // =========================================================================

    public function testOrderBySetsOrderByClause()
    {
        $sql = Query::select()->from('users')->orderBy('name ASC')->getQuery();
        assert_contains('ORDER BY name ASC', $sql);
    }

    // =========================================================================
    // Fluent setters: limit()
    // =========================================================================

    public function testLimitWithPositiveIntegerAddsLimitClause()
    {
        $sql = Query::select()->from('t')->limit(10)->getQuery();
        assert_contains('LIMIT 10', $sql);
    }

    public function testLimitWithZeroDoesNotAddLimitClauseInSelect()
    {
        // limit(0) means "no LIMIT" (backward-compat with array-constructor default)
        $sql = Query::select()->from('t')->limit(0)->getQuery();
        assert_not_contains('LIMIT', $sql);
    }

    public function testLimitWithNegativeThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select()->from('t')->limit(-1);
        });
    }

    public function testLimitWithFloatThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select()->from('t')->limit(1.5);
        });
    }

    public function testLimitWithNonNumericStringThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select()->from('t')->limit('ten');
        });
    }

    // =========================================================================
    // Fluent setters: offset()
    // =========================================================================

    public function testOffsetWithPositiveIntegerAddsOffsetClause()
    {
        $sql = Query::select()->from('t')->offset(5)->getQuery();
        assert_contains('OFFSET 5', $sql);
    }

    public function testOffsetWithZeroEmitsOffsetZero()
    {
        // offset=0 is a valid SQL expression ("skip 0 rows"); it should be emitted
        $sql = Query::select()->from('t')->offset(0)->getQuery();
        assert_contains('OFFSET 0', $sql);
    }

    public function testOffsetWithNegativeThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select()->from('t')->offset(-5);
        });
    }

    public function testOffsetWithFloatThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select()->from('t')->offset(2.9);
        });
    }

    public function testOffsetWithNonNumericStringThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::select()->from('t')->offset('many');
        });
    }

    // =========================================================================
    // Fluent setters: valuesCount()
    // =========================================================================

    public function testValuesCountWithPositiveIntegerSetsRowCount()
    {
        $sql = Query::insert('t', ['a'])->valuesCount(3)->getQuery();
        assert_contains(':a_2', $sql); // row indices 0, 1, 2
    }

    public function testValuesCountOneIsEquivalentToDefault()
    {
        $sqlDefault = Query::insert('t', ['a'])->getQuery();
        $sqlOne     = Query::insert('t', ['a'])->valuesCount(1)->getQuery();
        assert_equals($sqlDefault, $sqlOne);
    }

    public function testValuesCountWithZeroThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::insert('t', ['a'])->valuesCount(0);
        });
    }

    public function testValuesCountWithNegativeThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::insert('t', ['a'])->valuesCount(-2);
        });
    }

    public function testValuesCountWithFloatThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::insert('t', ['a'])->valuesCount(2.5);
        });
    }

    public function testValuesCountWithNonNumericStringThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::insert('t', ['a'])->valuesCount('many');
        });
    }

    // =========================================================================
    // Cache invalidation — setter after build must reset cached SQL
    // =========================================================================

    public function testWhereInvalidatesBuiltQuery()
    {
        $q  = Query::select()->from('users');
        $s1 = $q->getQuery();
        $q->where('active = 1');
        $s2 = $q->getQuery();
        assert_true($s1 !== $s2, 'where() after build must produce different SQL');
        assert_contains('WHERE active = 1', $s2);
    }

    public function testFromInvalidatesBuiltQuery()
    {
        $q  = Query::select()->from('old_table');
        $s1 = $q->getQuery();
        $q->from('new_table');
        $s2 = $q->getQuery();
        assert_true($s1 !== $s2);
        assert_contains('new_table', $s2);
        assert_not_contains('old_table', $s2);
    }

    public function testLimitInvalidatesBuiltQuery()
    {
        $q  = Query::select()->from('t');
        $s1 = $q->getQuery();
        $q->limit(5);
        $s2 = $q->getQuery();
        assert_true($s1 !== $s2);
        assert_contains('LIMIT 5', $s2);
    }

    public function testOrderByInvalidatesBuiltQuery()
    {
        $q  = Query::select()->from('t');
        $s1 = $q->getQuery();
        $q->orderBy('id DESC');
        $s2 = $q->getQuery();
        assert_true($s1 !== $s2);
        assert_contains('ORDER BY id DESC', $s2);
    }

    public function testJoinInvalidatesBuiltQuery()
    {
        $q  = Query::select()->from('users');
        $s1 = $q->getQuery();
        $q->join('LEFT JOIN orders ON users.id = orders.user_id');
        $s2 = $q->getQuery();
        assert_true($s1 !== $s2);
        assert_contains('LEFT JOIN orders', $s2);
    }

    // =========================================================================
    // buildSelectQuery — full SQL output and all clauses
    // =========================================================================

    public function testSelectFullSqlWithAllClauses()
    {
        $sql = Query::select(['id', 'name'])
            ->from('users')
            ->join('LEFT JOIN orders ON users.id = orders.user_id')
            ->where('users.active = 1')
            ->groupBy('users.id')
            ->having('COUNT(orders.id) > 0')
            ->orderBy('users.name ASC')
            ->limit(10)
            ->offset(5)
            ->getQuery();

        $expected = 'SELECT id, name FROM users'
            . ' LEFT JOIN orders ON users.id = orders.user_id'
            . ' WHERE users.active = 1'
            . ' GROUP BY users.id'
            . ' HAVING COUNT(orders.id) > 0'
            . ' ORDER BY users.name ASC'
            . ' LIMIT 10'
            . ' OFFSET 5';

        assert_equals($expected, $sql);
    }

    public function testSelectArrayConstructorFullClauses()
    {
        $q = new Query([
            'method'   => 'SELECT',
            'fields'   => ['id', 'name'],
            'table'    => 'users',
            'joins'    => ['LEFT JOIN orders ON users.id = orders.user_id'],
            'where'    => 'users.active = 1',
            'group_by' => 'users.id',
            'having'   => 'COUNT(orders.id) > 0',
            'order_by' => 'users.name ASC',
            'limit'    => 10,
            'offset'   => 5,
        ]);
        $expected = 'SELECT id, name FROM users'
            . ' LEFT JOIN orders ON users.id = orders.user_id'
            . ' WHERE users.active = 1'
            . ' GROUP BY users.id'
            . ' HAVING COUNT(orders.id) > 0'
            . ' ORDER BY users.name ASC'
            . ' LIMIT 10'
            . ' OFFSET 5';
        assert_equals($expected, $q->getQuery());
    }

    public function testSelectWithNoFieldsInArrayConstructorDefaultsToWildcard()
    {
        $q = new Query(['method' => 'SELECT', 'table' => 'users']);
        assert_equals('SELECT * FROM users', $q->getQuery());
    }

    public function testSelectLimitZeroOmitsLimitClause()
    {
        $sql = Query::select()->from('t')->limit(0)->getQuery();
        assert_not_contains('LIMIT', $sql);
    }

    public function testSelectArrayConstructorLimitZeroOmitsLimitClause()
    {
        // Default Database behavior passes limit=0 when no limit is intended
        $q = new Query(['method' => 'SELECT', 'table' => 't', 'limit' => 0]);
        assert_not_contains('LIMIT', $q->getQuery());
    }

    public function testSelectArrayConstructorStringSqlInjectionInLimitIsIgnored()
    {
        // filter_var rejects the malicious string — no LIMIT and no injected SQL
        $q = new Query([
            'method' => 'SELECT',
            'table'  => 't',
            'limit'  => '5; DROP TABLE users',
        ]);
        $sql = $q->getQuery();
        assert_not_contains('DROP', $sql);
        assert_not_contains('LIMIT', $sql);
    }

    public function testSelectArrayConstructorValidStringLimitIsAccepted()
    {
        // filter_var accepts '5' as integer 5
        $q = new Query(['method' => 'SELECT', 'table' => 't', 'limit' => '5']);
        assert_contains('LIMIT 5', $q->getQuery());
    }

    public function testSelectOffsetZeroIsEmitted()
    {
        // Unlike LIMIT 0, OFFSET 0 is emitted as it is a valid SQL expression
        $sql = Query::select()->from('t')->limit(10)->offset(0)->getQuery();
        assert_contains('OFFSET 0', $sql);
    }

    public function testSelectWithMultipleJoins()
    {
        $sql = Query::select()->from('users')
            ->joins([
                'LEFT JOIN a ON a.id = users.a_id',
                'INNER JOIN b ON b.id = users.b_id',
            ])
            ->getQuery();
        assert_contains('LEFT JOIN a', $sql);
        assert_contains('INNER JOIN b', $sql);
    }

    public function testSelectMissingTableInArrayConstructorThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            new Query(['method' => 'SELECT', 'fields' => ['id']]);
        });
    }

    // =========================================================================
    // buildPDOInsertQuery — placeholders
    // =========================================================================

    public function testInsertSingleRowPlaceholders()
    {
        $sql = Query::insert('users', ['name', 'email'])->getQuery();
        assert_equals('INSERT INTO users (name, email) VALUES (:name_0, :email_0)', $sql);
    }

    public function testInsertThreeRowsPlaceholders()
    {
        $sql      = Query::insert('products', ['sku', 'price'])->valuesCount(3)->getQuery();
        $expected = 'INSERT INTO products (sku, price)'
            . ' VALUES (:sku_0, :price_0), (:sku_1, :price_1), (:sku_2, :price_2)';
        assert_equals($expected, $sql);
    }

    public function testInsertMissingTableInArrayConstructorThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            new Query(['method' => 'INSERT', 'fields' => ['name']]);
        });
    }

    public function testInsertEmptyFieldsThrowsAtBuildTime()
    {
        $q = Query::insert('t')->fields([]); // empty array allowed by setter; builder rejects it
        assert_throws('InvalidArgumentException', function () use ($q) {
            $q->getQuery();
        });
    }

    // =========================================================================
    // buildPDOUpdateQuery — SET clause
    // =========================================================================

    public function testUpdateSetClauseMultipleFields()
    {
        $sql = Query::update('users', ['first_name', 'last_name', 'email'])
            ->where('id = :id')
            ->getQuery();
        assert_contains('SET first_name = :first_name, last_name = :last_name, email = :email', $sql);
    }

    public function testUpdateWithJoin()
    {
        $sql      = Query::update('users', ['status'])
            ->join('LEFT JOIN orders ON users.id = orders.user_id')
            ->where('orders.amount > :amount')
            ->getQuery();
        $expected = 'UPDATE users'
            . ' LEFT JOIN orders ON users.id = orders.user_id'
            . ' SET status = :status'
            . ' WHERE orders.amount > :amount';
        assert_equals($expected, $sql);
    }

    public function testUpdateWithoutWhereClauses()
    {
        $sql = Query::update('settings', ['value'])->getQuery();
        assert_equals('UPDATE settings SET value = :value', $sql);
    }

    public function testUpdateMissingTableInArrayConstructorThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            new Query(['method' => 'UPDATE', 'fields' => ['name']]);
        });
    }

    public function testUpdateEmptyFieldsThrowsAtBuildTime()
    {
        $q = Query::update('t')->fields([]);
        assert_throws('InvalidArgumentException', function () use ($q) {
            $q->getQuery();
        });
    }

    // =========================================================================
    // buildDeleteQuery
    // =========================================================================

    public function testDeleteBasicSql()
    {
        assert_equals('DELETE FROM users', Query::delete('users')->getQuery());
    }

    public function testDeleteWithWhereSql()
    {
        $sql = Query::delete('users')->where('id = :id')->getQuery();
        assert_equals('DELETE FROM users WHERE id = :id', $sql);
    }

    public function testDeleteWithOrderBy()
    {
        $sql = Query::delete('logs')->orderBy('created_at ASC')->getQuery();
        assert_contains('ORDER BY created_at ASC', $sql);
    }

    public function testDeleteWithLimitGreaterThanZero()
    {
        $sql = Query::delete('logs')->limit(100)->getQuery();
        assert_contains('LIMIT 100', $sql);
    }

    public function testDeleteWithLimitZeroOmitsLimitClause()
    {
        $sql = Query::delete('logs')->limit(0)->getQuery();
        assert_not_contains('LIMIT', $sql);
    }

    public function testDeleteArrayConstructorLimitZeroOmitsLimit()
    {
        $q = new Query(['method' => 'DELETE', 'table' => 'logs', 'limit' => 0]);
        assert_not_contains('LIMIT', $q->getQuery());
    }

    public function testDeleteArrayConstructorNoLimitKeyOmitsLimit()
    {
        $q = new Query(['method' => 'DELETE', 'table' => 'logs']);
        assert_not_contains('LIMIT', $q->getQuery());
    }

    public function testDeleteMissingTableInArrayConstructorThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            new Query(['method' => 'DELETE']);
        });
    }

    public function testDeleteWithWhereOrderByAndLimit()
    {
        $sql      = Query::delete('logs')
            ->where('created_at < :cutoff')
            ->orderBy('created_at ASC')
            ->limit(500)
            ->getQuery();
        $expected = 'DELETE FROM logs WHERE created_at < :cutoff ORDER BY created_at ASC LIMIT 500';
        assert_equals($expected, $sql);
    }

    // =========================================================================
    // validateOrderBy
    // =========================================================================

    public function testValidateOrderBySingleColumn()
    {
        assert_equals('name', Query::validateOrderBy('name'));
    }

    public function testValidateOrderBySingleColumnWithAsc()
    {
        assert_equals('name ASC', Query::validateOrderBy('name ASC'));
    }

    public function testValidateOrderBySingleColumnWithDesc()
    {
        assert_equals('created_at DESC', Query::validateOrderBy('created_at DESC'));
    }

    public function testValidateOrderByMultipleColumns()
    {
        assert_equals('name ASC, id DESC', Query::validateOrderBy('name ASC, id DESC'));
    }

    public function testValidateOrderByTableQualified()
    {
        assert_equals('users.name', Query::validateOrderBy('users.name'));
    }

    public function testValidateOrderByTableQualifiedWithDirection()
    {
        assert_equals('users.created_at DESC', Query::validateOrderBy('users.created_at DESC'));
    }

    public function testValidateOrderByUnderscoreInColumnName()
    {
        assert_equals('created_at', Query::validateOrderBy('created_at'));
    }

    public function testValidateOrderByTrimsWhitespace()
    {
        assert_equals('name ASC', Query::validateOrderBy('  name ASC  '));
    }

    public function testValidateOrderByCaseInsensitiveDirection()
    {
        assert_equals('name asc', Query::validateOrderBy('name asc'));
    }

    public function testValidateOrderByEmptyStringThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::validateOrderBy('');
        });
    }

    public function testValidateOrderByWhitespaceOnlyThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::validateOrderBy('   ');
        });
    }

    public function testValidateOrderBySqlInjectionSemicolonThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::validateOrderBy('name; DROP TABLE users');
        });
    }

    public function testValidateOrderBySqlInjectionUnionThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::validateOrderBy('name UNION SELECT password FROM users');
        });
    }

    public function testValidateOrderByStartsWithDigitThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::validateOrderBy('1name');
        });
    }

    public function testValidateOrderByTrailingCommaThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::validateOrderBy('name,');
        });
    }

    public function testValidateOrderByNonStringIntegerThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::validateOrderBy(123);
        });
    }

    public function testValidateOrderByNonStringArrayThrows()
    {
        assert_throws('InvalidArgumentException', function () {
            Query::validateOrderBy(['name']);
        });
    }

    public function testSelectInvalidOrderByThrowsViaGetQuery()
    {
        $q = Query::select()->from('t')->orderBy('INVALID!! ORDER');
        assert_throws('InvalidArgumentException', function () use ($q) {
            $q->getQuery();
        });
    }

    public function testSelectInvalidOrderByViaToStringEmitsWarning()
    {
        $q      = Query::select()->from('t')->orderBy('INVALID!! ORDER');
        $result = $this->captureWarnings(function () use ($q) {
            return (string) $q;
        });
        assert_equals('', $result['result']);
        assert_equals(1, count($result['warnings']));
    }

    // =========================================================================
    // buildQuery — unsupported / missing method
    // =========================================================================

    public function testUnsupportedMethodThrowsViaArrayConstructor()
    {
        assert_throws('InvalidArgumentException', function () {
            new Query(['method' => 'TRUNCATE', 'table' => 'users']);
        });
    }

    public function testMissingMethodThrowsViaGetQuery()
    {
        $q = new Query(); // no method set
        assert_throws('InvalidArgumentException', function () use ($q) {
            $q->getQuery();
        });
    }
}