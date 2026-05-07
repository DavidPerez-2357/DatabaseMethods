<?php

/**
 * tests/QueryRunTests.php
 *
 * Focused integration tests for Query::run(), kept intentionally minimal.
 */
class QueryRunTests
{
    /** @var Database */
    private $db;

    /** @var string */
    private $dbFile;

    const TABLE = 'test_query_run';

    public function __construct()
    {
        $this->dbFile = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'dbmethods_query_run_'
            . getmypid()
            . '_'
            . uniqid('', true)
            . '.sqlite';

        $this->db = new Sqlite(array('DB' => $this->dbFile));
        $this->db->runPlainQuery(
            'CREATE TABLE ' . self::TABLE . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT
            )'
        );
    }

    public function teardown()
    {
        try {
            $this->db->runPlainQuery('DROP TABLE IF EXISTS ' . self::TABLE);
        } catch (Exception $e) {
            // Best effort cleanup.
        }
        $this->db = null;
        if (file_exists($this->dbFile)) {
            if (!unlink($this->dbFile)) {
                throw new RuntimeException(
                    'Teardown failed: could not remove temporary database file: ' . $this->dbFile
                );
            }
        }
    }

    public function testRunSelectWithSetDatabaseReturnsRows()
    {
        $this->db->insert(self::TABLE, array('name' => 'Alice', 'email' => 'alice@example.com'));

        $query = Query::select(array('name'))
            ->from(self::TABLE)
            ->where('name = :name');
        $query->setDatabase($this->db);

        $rows = $query->run(array('name' => 'Alice'));

        assert_equals(1, count($rows));
        assert_equals('Alice', $rows[0]['name']);
    }

    public function testRunWriteOperationsAndGuards()
    {
        $id = $this->db->createQuery()
            ->insert(self::TABLE, array('name', 'email'))
            ->run(array('name' => 'Bob', 'email' => 'bob@example.com'));
        assert_true(is_int($id));
        assert_true($id > 0);

        $updated = $this->db->createQuery()
            ->update(self::TABLE, array('name'))
            ->where('id = :id')
            ->run(array('name' => 'Bobby', 'id' => $id));
        assert_equals(1, $updated);

        $deleted = $this->db->createQuery()
            ->delete(self::TABLE)
            ->where('id = :id')
            ->run(array('id' => $id));
        assert_equals(1, $deleted);

        $batchInsertResult = $this->db->createQuery()
            ->insert(self::TABLE, array('name', 'email'))
            ->run(array(
                array('name' => 'Alice 1', 'email' => 'alice1@example.com'),
                array('name' => 'Alice 2', 'email' => 'alice2@example.com'),
            ));
        assert_equals(0, $batchInsertResult);
        $batchRows = $this->db->plainSelect(
            'SELECT id FROM ' . self::TABLE . ' WHERE name IN (:a, :b)',
            array('a' => 'Alice 1', 'b' => 'Alice 2')
        );
        assert_equals(2, count($batchRows));

        assert_throws(
            'InvalidArgumentException',
            function () {
                $this->db->createQuery()->insert(self::TABLE, array('name'))->run();
            }
        );

        assert_throws(
            'InvalidArgumentException',
            function () {
                $this->db->createQuery()
                    ->update(self::TABLE)
                    ->where('id = :id')
                    ->run(array('id' => 1));
            }
        );

        assert_throws(
            'InvalidArgumentException',
            function () {
                $this->db->createQuery()
                    ->update(self::TABLE, array('name'))
                    ->where('id = :id')
                    ->run(array('id' => 1));
            }
        );

        assert_throws(
            'RuntimeException',
            function () {
                Query::select(array('id'))->from(self::TABLE)->run();
            }
        );

        $emptyQuery = $this->db->createQuery();
        assert_throws(
            'InvalidArgumentException',
            function () use ($emptyQuery) {
                $emptyQuery->run();
            }
        );
    }
}
