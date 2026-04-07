<?php
/**
 * tests/DatabaseTest.php
 *
 * Integration test suite for the Database class.
 *
 * Covers: select, selectOne, insert (single + multiple), update, delete,
 * deleteAll, count, plainSelect, executePlainQuery, executeTransaction,
 * setJsonEncode, and getLastInsertId.
 *
 * A temporary SQLite database file is created automatically before the
 * tests run and deleted when teardown() is called. No configuration is
 * needed for SQLite.
 *
 * To test against MySQL, PostgreSQL, or SQL Server instead:
 *   1. Change DB_TEST_DRIVER to 'mysql', 'postgres', or 'sql'.
 *   2. Fill in DB_TEST_HOST, DB_TEST_USER, DB_TEST_PASS, and DB_TEST_NAME.
 *   3. Run: php tests/run.php
 *
 * @author DavidPerez-2357
 * @link   https://github.com/DavidPerez-2357/DatabaseMethods
 */

// ============================================================
// CONNECTION CONFIGURATION
// Adjust the values below before running the tests.
// SQLite requires no additional setup; a temporary file is used.
// ============================================================

/** Driver to use: 'sqlite', 'mysql', 'postgres', or 'sql'. */
define('DB_TEST_DRIVER', 'sqlite');

/** SQLite only: absolute path for a unique temporary database file. */
define(
    'DB_TEST_FILE',
    sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'dbmethods_test_'
    . getmypid()
    . '_'
    . uniqid('', true)
    . '.sqlite'
);

// MySQL / PostgreSQL / SQL Server — fill in your credentials:
define('DB_TEST_HOST',         'localhost');
define('DB_TEST_USER',         'root');
define('DB_TEST_PASS',         '');
define('DB_TEST_NAME',         'test_dbmethods');  // Database to CREATE / DROP
define('DB_TEST_CODIFICATION', 'utf8mb4');         // MySQL / PostgreSQL only

// ============================================================

class DatabaseTest
{
    /** @var Database */
    private $db;

    /** Table name used by all tests. */
    const TABLE = 'test_users';

    public function __construct()
    {
        $this->db = $this->createDriver();
        $this->createTable();
    }

    // =========================================================================
    // Lifecycle
    // =========================================================================

    /**
     * Creates and returns a driver instance for the configured DB_TEST_DRIVER.
     * SQLite uses the temporary file configured in DB_TEST_FILE. Server-based
     * drivers may require the target test database to exist already, depending
     * on the setup method used for the selected driver.
     */
    private function createDriver()
    {
        switch (DB_TEST_DRIVER) {
            case 'mysql':
                return $this->setupMysql();
            case 'postgres':
                return $this->setupPostgres();
            case 'sql':
                return $this->setupSql();
            default: // sqlite
                return new Sqlite(['DB' => DB_TEST_FILE]);
        }
    }

    private function setupMysql()
    {
        // Connect without a database first so we can CREATE the test database.
        $adminConn = new PDO(
            'mysql:host=' . DB_TEST_HOST . ';charset=' . DB_TEST_CODIFICATION,
            DB_TEST_USER,
            DB_TEST_PASS
        );
        $adminConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $adminConn->exec('CREATE DATABASE IF NOT EXISTS ' . DB_TEST_NAME);

        return new Mysql([
            'serverName'   => DB_TEST_HOST,
            'username'     => DB_TEST_USER,
            'password'     => DB_TEST_PASS,
            'DB'           => DB_TEST_NAME,
            'codification' => DB_TEST_CODIFICATION,
        ]);
    }

    private function setupPostgres()
    {
        // Connect to the default 'postgres' maintenance database to create the test database.
        $adminConn = new PDO(
            'pgsql:host=' . DB_TEST_HOST . ';dbname=postgres',
            DB_TEST_USER,
            DB_TEST_PASS
        );
        $adminConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $adminConn->exec('CREATE DATABASE ' . DB_TEST_NAME);
        } catch (PDOException $e) {
            // PostgreSQL does not support "CREATE DATABASE IF NOT EXISTS".
            // Ignore the duplicate_database error so test setup is idempotent.
            if ($e->getCode() !== '42P04') {
                throw $e;
            }
        }
        return new Postgres([
            'serverName'   => DB_TEST_HOST,
            'username'     => DB_TEST_USER,
            'password'     => DB_TEST_PASS,
            'DB'           => DB_TEST_NAME,
            'codification' => DB_TEST_CODIFICATION,
        ]);
    }

    private function setupSql()
    {
        return new Sql([
            'serverName' => DB_TEST_HOST,
            'username'   => DB_TEST_USER,
            'password'   => DB_TEST_PASS,
            'DB'         => DB_TEST_NAME,
        ]);
    }

    /**
     * Drops the test table and, where supported, removes the test database or
     * file. SQLite deletes DB_TEST_FILE; MySQL and PostgreSQL drop DB_TEST_NAME.
     * SQL Server only drops the test table — it does not create or drop a
     * database, so no extra cleanup is needed.
     * Must be called after all tests have run.
     */
    public function teardown()
    {
        try {
            $this->db->executePlainQuery($this->getDropTableSql());
        } catch (Exception $e) {
            // Best-effort; ignore if already gone.
        }

        switch (DB_TEST_DRIVER) {
            case 'sqlite':
                if (file_exists(DB_TEST_FILE)) {
                    @unlink(DB_TEST_FILE);
                }
                break;

            case 'mysql':
                try {
                    $conn = new PDO(
                        'mysql:host=' . DB_TEST_HOST . ';charset=' . DB_TEST_CODIFICATION,
                        DB_TEST_USER,
                        DB_TEST_PASS
                    );
                    $conn->exec('DROP DATABASE IF EXISTS ' . DB_TEST_NAME);
                } catch (Exception $e) {
                    // Best-effort.
                }
                break;

            case 'postgres':
                try {
                    $conn = new PDO(
                        'pgsql:host=' . DB_TEST_HOST . ';dbname=postgres',
                        DB_TEST_USER,
                        DB_TEST_PASS
                    );
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    // Terminate any remaining connections to the test database before dropping it.
                    $conn->exec(
                        "SELECT pg_terminate_backend(pid) FROM pg_stat_activity"
                        . " WHERE datname = '" . DB_TEST_NAME . "'"
                    );
                    $conn->exec('DROP DATABASE IF EXISTS ' . DB_TEST_NAME);
                } catch (Exception $e) {
                    // Best-effort.
                }
                break;
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /** Returns the CREATE TABLE SQL appropriate for the configured driver. */
    private function getCreateTableSql()
    {
        switch (DB_TEST_DRIVER) {
            case 'mysql':
                return 'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' ('
                    . 'id INT AUTO_INCREMENT PRIMARY KEY, '
                    . 'name VARCHAR(255) NOT NULL, '
                    . 'email VARCHAR(255) NOT NULL, '
                    . 'active TINYINT NOT NULL DEFAULT 1)';

            case 'postgres':
                return 'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' ('
                    . 'id SERIAL PRIMARY KEY, '
                    . 'name VARCHAR(255) NOT NULL, '
                    . 'email VARCHAR(255) NOT NULL, '
                    . 'active SMALLINT NOT NULL DEFAULT 1)';

            case 'sql':
                return "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='" . self::TABLE . "' AND xtype='U') "
                    . 'CREATE TABLE ' . self::TABLE . ' ('
                    . 'id INT IDENTITY(1,1) PRIMARY KEY, '
                    . 'name NVARCHAR(255) NOT NULL, '
                    . 'email NVARCHAR(255) NOT NULL, '
                    . 'active TINYINT NOT NULL DEFAULT 1)';

            default: // sqlite
                return 'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' ('
                    . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
                    . 'name TEXT NOT NULL, '
                    . 'email TEXT NOT NULL, '
                    . 'active INTEGER NOT NULL DEFAULT 1)';
        }
    }

    /** Returns the DROP TABLE SQL appropriate for the configured driver. */
    private function getDropTableSql()
    {
        if (DB_TEST_DRIVER === 'sql') {
            return "IF EXISTS (SELECT * FROM sysobjects WHERE name='" . self::TABLE . "' AND xtype='U') "
                . 'DROP TABLE ' . self::TABLE;
        }
        return 'DROP TABLE IF EXISTS ' . self::TABLE;
    }

    private function createTable()
    {
        $this->db->executePlainQuery($this->getCreateTableSql());
    }

    /**
     * Drops and recreates the test table, resetting all rows and the
     * auto-increment counter. Call at the start of each test.
     */
    private function resetTable()
    {
        $this->db->executePlainQuery($this->getDropTableSql());
        $this->db->executePlainQuery($this->getCreateTableSql());
    }

    // =========================================================================
    // Tests — insert
    // =========================================================================

    public function testInsertSingleRecordReturnsPositiveId()
    {
        $this->resetTable();
        $id = $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);

        if (DB_TEST_DRIVER === 'postgres') {
            // PDO::lastInsertId() requires a sequence name on PostgreSQL; without
            // it, the value may be 0. Only assert the return type is an integer.
            assert_true(is_int($id), 'insert() should return an integer on PostgreSQL.');
            return;
        }
        assert_true(is_int($id) && $id > 0, 'insert() should return a positive integer last-insert ID.');
    }

    public function testInsertSingleRecordIsPersisted()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Bob', 'email' => 'bob@example.com']);
        $rows = $this->db->plainSelect('SELECT * FROM ' . self::TABLE);
        assert_equals(1, count($rows));
        assert_equals('Bob', $rows[0]['name']);
        assert_equals('bob@example.com', $rows[0]['email']);
    }

    public function testInsertMultipleRecordsReturnsPositiveId()
    {
        $this->resetTable();
        $data = [
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob',   'email' => 'bob@example.com'],
            ['name' => 'Carol', 'email' => 'carol@example.com'],
        ];
        $lastId = $this->db->insert(self::TABLE, $data);

        assert_equals(3, $this->db->count(self::TABLE), 'Multi-row insert() should persist all rows.');

        if (DB_TEST_DRIVER === 'postgres') {
            assert_true(is_int($lastId), 'Multi-row insert() should return an integer on PostgreSQL, even if PDO cannot provide a positive last-insert ID without a sequence name.');
            return;
        }
        assert_true(is_int($lastId) && $lastId > 0, 'Multi-row insert() should return a positive integer last-insert ID.');
    }

    public function testInsertMultipleRecordsAreAllPersisted()
    {
        $this->resetTable();
        $data = [
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob',   'email' => 'bob@example.com'],
        ];
        $this->db->insert(self::TABLE, $data);
        assert_equals(2, $this->db->count(self::TABLE));
    }

    // =========================================================================
    // Tests — select / selectOne
    // =========================================================================

    public function testSelectReturnsAllRows()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->db->insert(self::TABLE, ['name' => 'Bob',   'email' => 'bob@example.com']);

        $query = Query::select()->from(self::TABLE);
        $rows  = $this->db->select($query);
        assert_equals(2, count($rows));
    }

    public function testSelectReturnsAssociativeArray()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);

        $query = Query::select(['name', 'email'])->from(self::TABLE);
        $rows  = $this->db->select($query);
        assert_true(array_key_exists('name',  $rows[0]));
        assert_true(array_key_exists('email', $rows[0]));
    }

    public function testSelectWithNamedWhereFiltersRows()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com', 'active' => 1]);
        $this->db->insert(self::TABLE, ['name' => 'Bob',   'email' => 'bob@example.com',   'active' => 0]);

        $query = Query::select()->from(self::TABLE)->where('active = :active');
        $rows  = $this->db->select($query, ['active' => 1]);
        assert_equals(1, count($rows));
        assert_equals('Alice', $rows[0]['name']);
    }

    public function testSelectOneReturnsSingleRow()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->db->insert(self::TABLE, ['name' => 'Bob',   'email' => 'bob@example.com']);

        $query = Query::select()->from(self::TABLE)->where('name = :name');
        $row   = $this->db->selectOne($query, ['name' => 'Alice']);
        assert_equals('Alice', $row['name']);
    }

    public function testSelectOneReturnsEmptyArrayWhenNoMatch()
    {
        $this->resetTable();
        $query = Query::select()->from(self::TABLE)->where('name = :name');
        $row   = $this->db->selectOne($query, ['name' => 'Nobody']);
        assert_equals([], $row);
    }

    // =========================================================================
    // Tests — update
    // =========================================================================

    public function testUpdateModifiesRecord()
    {
        $this->resetTable();
        $id = $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->db->update(self::TABLE, ['name' => 'Alicia'], 'id = :row_id', ['row_id' => $id]);

        $query = Query::select()->from(self::TABLE)->where('id = :id');
        $row   = $this->db->selectOne($query, ['id' => $id]);
        assert_equals('Alicia', $row['name']);
    }

    public function testUpdateReturnsAffectedRowCount()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com', 'active' => 1]);
        $this->db->insert(self::TABLE, ['name' => 'Bob',   'email' => 'bob@example.com',   'active' => 1]);

        $affected = $this->db->update(self::TABLE, ['active' => 0], 'active = :a', ['a' => 1]);
        assert_equals(2, $affected);
    }

    public function testUpdateWithNoMatchReturnsZero()
    {
        $this->resetTable();
        $affected = $this->db->update(self::TABLE, ['name' => 'Ghost'], 'id = :id', ['id' => 9999]);
        assert_equals(0, $affected);
    }

    // =========================================================================
    // Tests — delete
    // =========================================================================

    public function testDeleteRemovesMatchingRow()
    {
        $this->resetTable();
        $id = $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->db->insert(self::TABLE, ['name' => 'Bob',   'email' => 'bob@example.com']);

        $this->db->delete(self::TABLE, 'id = :id', ['id' => $id]);
        assert_equals(1, $this->db->count(self::TABLE));
    }

    public function testDeleteReturnsAffectedRowCount()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com', 'active' => 1]);
        $this->db->insert(self::TABLE, ['name' => 'Bob',   'email' => 'bob@example.com',   'active' => 1]);

        $affected = $this->db->delete(self::TABLE, 'active = :a', ['a' => 1]);
        assert_equals(2, $affected);
        assert_equals(0, $this->db->count(self::TABLE));
    }

    public function testDeleteWithPositionalPlaceholder()
    {
        $this->resetTable();
        $id       = $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);
        $affected = $this->db->delete(self::TABLE, 'id = ?', [$id]);
        assert_equals(1, $affected);
        assert_equals(0, $this->db->count(self::TABLE));
    }

    // =========================================================================
    // Tests — deleteAll
    // =========================================================================

    public function testDeleteAllRemovesAllRows()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->db->insert(self::TABLE, ['name' => 'Bob',   'email' => 'bob@example.com']);

        $affected = $this->db->deleteAll(self::TABLE);
        assert_equals(2, $affected);
        assert_equals(0, $this->db->count(self::TABLE));
    }

    public function testDeleteAllOnEmptyTableReturnsZero()
    {
        $this->resetTable();
        assert_equals(0, $this->db->deleteAll(self::TABLE));
    }

    // =========================================================================
    // Tests — count
    // =========================================================================

    public function testCountReturnsTotal()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->db->insert(self::TABLE, ['name' => 'Bob',   'email' => 'bob@example.com']);
        assert_equals(2, $this->db->count(self::TABLE));
    }

    public function testCountOnEmptyTableReturnsZero()
    {
        $this->resetTable();
        assert_equals(0, $this->db->count(self::TABLE));
    }

    public function testCountWithNamedWhere()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com', 'active' => 1]);
        $this->db->insert(self::TABLE, ['name' => 'Bob',   'email' => 'bob@example.com',   'active' => 0]);

        assert_equals(1, $this->db->count(self::TABLE, 'active = :a', ['a' => 1]));
        assert_equals(1, $this->db->count(self::TABLE, 'active = :a', ['a' => 0]));
    }

    public function testCountWithPositionalWhere()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com', 'active' => 1]);
        $this->db->insert(self::TABLE, ['name' => 'Bob',   'email' => 'bob@example.com',   'active' => 0]);

        assert_equals(1, $this->db->count(self::TABLE, 'active = ?', [1]));
    }

    // =========================================================================
    // Tests — plainSelect
    // =========================================================================

    public function testPlainSelectReturnsAllRows()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->db->insert(self::TABLE, ['name' => 'Bob',   'email' => 'bob@example.com']);

        $rows = $this->db->plainSelect('SELECT * FROM ' . self::TABLE);
        assert_equals(2, count($rows));
    }

    public function testPlainSelectWithNamedBindings()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com', 'active' => 1]);
        $this->db->insert(self::TABLE, ['name' => 'Bob',   'email' => 'bob@example.com',   'active' => 0]);

        $rows = $this->db->plainSelect(
            'SELECT * FROM ' . self::TABLE . ' WHERE active = :a',
            ['a' => 1]
        );
        assert_equals(1, count($rows));
        assert_equals('Alice', $rows[0]['name']);
    }

    // =========================================================================
    // Tests — executePlainQuery
    // =========================================================================

    public function testExecutePlainQueryReturnsTrueOnSuccess()
    {
        $this->resetTable();
        $result = $this->db->executePlainQuery(
            'INSERT INTO ' . self::TABLE . ' (name, email) VALUES (:name, :email)',
            [':name' => 'Alice', ':email' => 'alice@example.com']
        );
        assert_true($result === true);
        assert_equals(1, $this->db->count(self::TABLE));
    }

    // =========================================================================
    // Tests — executeTransaction
    // =========================================================================

    public function testExecuteTransactionCommitsOnSuccess()
    {
        $this->resetTable();
        $this->db->executeTransaction(function ($db) {
            $db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);
            $db->insert(self::TABLE, ['name' => 'Bob',   'email' => 'bob@example.com']);
        });
        assert_equals(2, $this->db->count(self::TABLE));
    }

    public function testExecuteTransactionRollsBackOnException()
    {
        $this->resetTable();
        try {
            $this->db->executeTransaction(function ($db) {
                $db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);
                throw new RuntimeException('Simulated failure');
            });
        } catch (RuntimeException $e) {
            // Expected — the transaction should have been rolled back.
        }
        assert_equals(0, $this->db->count(self::TABLE), 'Rolled-back transaction must leave no rows.');
    }

    // =========================================================================
    // Tests — setJsonEncode
    // =========================================================================

    public function testSetJsonEncodeTrueReturnsJsonStringFromSelect()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->db->setJsonEncode(true);
        try {
            $result = $this->db->select(Query::select(['name', 'email'])->from(self::TABLE));
            $this->db->setJsonEncode(false); // restore default
        } catch (Exception $e) {
            $this->db->setJsonEncode(false); // restore default on failure
            throw $e;
        }

        assert_true(is_string($result), 'select() should return a JSON string when setJsonEncode(true).');
        $decoded = json_decode($result, true);
        assert_true(is_array($decoded) && count($decoded) === 1);
        assert_equals('Alice', $decoded[0]['name']);
    }

    public function testSetJsonEncodeFalseReturnsArray()
    {
        $this->resetTable();
        $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->db->setJsonEncode(false);
        $result = $this->db->select(Query::select()->from(self::TABLE));
        assert_true(is_array($result));
    }

    // =========================================================================
    // Tests — getLastInsertId
    // =========================================================================

    public function testGetLastInsertIdMatchesInsertReturnValue()
    {
        $this->resetTable();
        $insertedId = $this->db->insert(self::TABLE, ['name' => 'Alice', 'email' => 'alice@example.com']);
        $lastId     = $this->db->getLastInsertId();
        assert_equals($insertedId, $lastId);
    }
}
