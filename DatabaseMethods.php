<?php
/**
 * DatabaseMethods.php
 *
 * This file contains methods for database management and manipulation.
 * It provides reusable functions for common operations such as querying, inserting,
 * updating, and deleting records in databases.
 *
 * @author DavidPerez-2357
 * @link https://github.com/DavidPerez-2357/DatabaseMethods
*/


/**
 * Query class to build SQL queries based on provided data.
 * Supports SELECT, INSERT, UPDATE, and DELETE methods.
 * 
 * @package DatabaseMethods
 */
class Query
{
    private array $data;
    private string $query;

    public function __construct(array $queryData)
    {
        $this->data = $queryData;
        $this->query = $this->buildQuery();
    }

    public function __tostring(): string
    {
        return $this->query;
    }

    public function buildQuery(): string
    {
        switch (strtoupper($this->data['method'] ?? '')) {
            case 'SELECT':
                return $this->buildSelectQuery();
            case 'INSERT':
                return $this->buildPDOInsertQuery();
            case 'UPDATE':
                return $this->buildPDOUpdateQuery();
            case 'DELETE':
                return $this->buildDeleteQuery();
            default:
                throw new InvalidArgumentException("Unsupported query method: " . $this->data['method']);
        }
    }

    /**
     * Builds a SELECT SQL query based on the provided data.
     * @throws InvalidArgumentException if the method is not SELECT or required fields are missing.
     * @return string The constructed SQL SELECT query.
     * @example
     * ```php
     * $query = new Query([
     *    'method' => 'SELECT',
     *    'fields' => ['id', 'name'],
     *    'table' => 'users',
     *    'joins' => ['LEFT JOIN orders ON users.id = orders.user_id'],
     *    'where' => 'users.active = 1',
     *    'group_by' => 'users.id',
     *    'having' => 'COUNT(orders.id) > 0',
     *    'order_by' => 'users.name ASC',
     *    'limit' => 10,
     *    'offset' => 0
     * * ]);
     * 
     */
    public function buildSelectQuery(): string
    {
        if (strtoupper($this->data['method'] ?? '') !== 'SELECT') {
            throw new InvalidArgumentException("Only SELECT queries are supported.");
        }

        $fields = isset($this->data['fields']) ? implode(", ", $this->data['fields']) : "*";
        $table = $this->data['table'] ?? throw new InvalidArgumentException("Table is required.");

        $sql = "SELECT {$fields} FROM {$table}";

        // Joins
        if (!empty($this->data['joins'])) {
            foreach ($this->data['joins'] as $join) {
                $sql .= " {$join}";
            }
        }

        // Where
        if (!empty($this->data['where'])) {
            $sql .= " WHERE {$this->data['where']}";
        }

        // Group by
        if (!empty($this->data['group_by'])) {
            $sql .= " GROUP BY {$this->data['group_by']}";
        }

        // Having
        if (!empty($this->data['having'])) {
            $sql .= " HAVING {$this->data['having']}";
        }

        // Order by
        if (!empty($this->data['order_by'])) {
            $sql .= " ORDER BY {$this->data['order_by']}";
        }

        // Limit
        if (!empty($this->data['limit']) && is_numeric($this->data['limit'])) {
            $sql .= " LIMIT {$this->data['limit']}";
        }

        // Offset
        if (!empty($this->data['offset']) && is_numeric($this->data['offset'])) {
            $sql .= " OFFSET {$this->data['offset']}";
        }

        return $sql;
    }

    /**
     * Builds an INSERT SQL query based on the provided data.
     * @throws InvalidArgumentException if the method is not INSERT or required fields are missing.
     * @return string The constructed SQL INSERT query.
     * @example
     * ```php
     * $query = new Query([
     *     'method' => 'INSERT',
     *     'table' => 'users',
     *     'fields' => ['name', 'email'],
     *     'values_to_insert' => 3
     * * ]);
     * */
    public function buildPDOInsertQuery(): string
    {
        if (strtoupper($this->data['method'] ?? '') !== 'INSERT') {
            throw new InvalidArgumentException("Only INSERT method is supported.");
        }

        $table = $this->data['table'] ?? throw new InvalidArgumentException("Table is required.");
        $fields = $this->data['fields'] ?? throw new InvalidArgumentException("Fields are required.");
        $values = $this->data['values_to_insert'] ?? 1;

        if (!is_array($fields) || empty($fields)) {
            throw new InvalidArgumentException("Fields must be a non-empty array.");
        }

        // Prepare placeholders for the query
        for ($i = 0; $i < $values; $i++) {
            $rowPlaceholders = [];
            foreach ($fields as $col) {
                $paramKey = ":{$col}_{$i}";
                $rowPlaceholders[] = $paramKey;
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $placeholders = implode(', ', $placeholders);
        $fieldsList = implode(', ', $fields);

        $sql = "INSERT INTO {$table} ({$fieldsList}) VALUES {$placeholders}";

        return $sql;
    }

    /**
     * Builds an UPDATE SQL query based on the provided data.
     * @throws InvalidArgumentException if the method is not UPDATE or required fields are missing.
     * @return array The constructed SQL UPDATE query and parameters.
     * @example
     * ```php
     * $query = new Query([
     *   'method' => 'UPDATE',
     *   'table' => 'users',
     *   'fields' => ['name', 'email'],
     *   'where' => 'id = 1',
     *   'joins' => ['LEFT JOIN orders ON users.id = orders.user_id']
     * ]);
     * */
    public function buildPDOUpdateQuery(): string
    {
        if (strtoupper($this->data['method'] ?? '') !== 'UPDATE') {
            throw new InvalidArgumentException("Only UPDATE method is supported.");
        }

        $table = $this->data['table'] ?? throw new InvalidArgumentException("Table is required.");
        $fields = $this->data['fields'] ?? throw new InvalidArgumentException("Fields are required.");

        if (!is_array($fields) || empty($fields)) {
            throw new InvalidArgumentException("Fields must be a non-empty array.");
        }

        // Prepare SET clause
        $setClauses = [];
        foreach ($fields as $col) {
            $paramKey = ":{$col}";
            $setClauses[] = "{$col} = {$paramKey}";
        }
        $setClause = implode(', ', $setClauses);

        $sql = "UPDATE {$table} SET {$setClause}";

        if (!empty($this->data['joins'])) {
            foreach ($this->data['joins'] as $join) {
                $sql .= " {$join}";
            }
        }

        // Prepare WHERE clause
        if (!empty($this->data['where'])) {
            $sql .= " WHERE {$this->data['where']}";
        }

        return $sql;
    }

    /**
     * Builds a DELETE SQL query based on the provided data.
     * @throws InvalidArgumentException if the method is not DELETE or required fields are missing.
     * @return string The constructed SQL DELETE query.
     * @example
     * ```php
     * $query = new Query([
     *    'method' => 'DELETE',
     *    'table' => 'users',
     *    'where' => 'id = 1',
     *    'order_by' => 'created_at DESC',
     *    'limit' => 10
     * ]);
     * */
    public function buildDeleteQuery(): string
    {
        if (strtoupper($this->data['method'] ?? '') !== 'DELETE') {
            throw new InvalidArgumentException("Only DELETE method is supported.");
        }

        $table = $this->data['table'] ?? throw new InvalidArgumentException("Table is required.");

        $sql = "DELETE FROM {$table}";

        // Where
        if (!empty($this->data['where'])) {
            $sql .= " WHERE {$this->data['where']}";
        }

        if (!empty($this->data["order_by"])) {
            $sql .= " ORDER BY {$this->data['order_by']}";
        }

        if (!empty($this->data["limit"])) {
            $limit = (int)$this->data["limit"];
            if ($limit > 0) {
                $sql .= " LIMIT {$limit}";
            }
        }

        return $sql;
    }
}

/**
 * Database class to handle database operations using PDO.
 * Provides methods for executing queries, inserting, updating, deleting, and selecting records.
 * 
 * @package DatabaseMethods
 */
class Database
{
    private $properties; // Array with the initial properties of the class
    private $conn; // conection variable

    function __construct($ppt)
    {
        $this->properties = $ppt;
    }

    protected function setConnection($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Executes a plain SQL query.
     * @param string $query The SQL query to execute.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return bool True on success, false on failure.
     */
    public function executePlainQuery(string $query, array $params = []): bool
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            // Use errorInfo for PDO
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        $result = $stmt->execute($params);

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        return true;
    }

    /**
     * Executes a plain SELECT SQL query and returns the results.
     * @param string $query The SQL SELECT query to execute.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return array The result set as an associative array.
     */
    public function executePlainSelectQuery(string $query, array $params = []): array
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            // Use errorInfo for PDO
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        $result = $stmt->execute($params);

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Executes a SELECT query using the Query class and returns a single row.
     * @param Query $query The Query object containing the SQL query.
     * @param array $params Optional parameters for the query.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return array The result row as an associative array.
     */
    public function selectOne(Query $query, array $params = []): array
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        $stmt = $this->conn->prepare((string) $query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        if (!$stmt->execute($params)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }
        
        // Fetch a single row as an associative array
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function select(Query $query, array $params = []): array
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        $stmt = $this->conn->prepare((string) $query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        if (!$stmt->execute($params)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        // Fetch all results as an associative array
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Inserts records into the specified table using the Query class.
     * This method detects if the data is a single record or multiple records and calls the appropriate method.
     * @param string $table The name of the table to insert into.
     * @param array $data An associative array of column names and values to insert, or an array of such arrays for multiple records.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The ID of the last inserted row or the number of affected rows for multiple inserts.
     */
    public function insert(string $table, array $data): int
    {
        // Detect if the data is a single record or multiple records
        if (isset($data[0]) && is_array($data[0])) {
            // Multiple records
            return $this->insertMany($table, $data);
        } else {
            // Single record
            return $this->insertOne($table, $data);
        }
    }

    /**
     * Inserts a single record into the specified table using the Query class.
     * @param string $table The name of the table to insert into.
     * @param array $data An associative array of column names and values to insert.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The ID of the last inserted row.
     */
    public function insertOne(string $table, array $data): int
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        $fields = array_keys($data);

        // Use the Query class to build the insert query
        $query = new Query([
            'method' => 'INSERT',
            'table' => $table,
            'fields' => $fields,
            'values_to_insert' => 1
        ]);

        $placeholders = [];
        foreach ($fields as $field) {
            $placeholders[":{$field}_0"] = $data[$field];
        }

        $stmt = $this->conn->prepare((string) $query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        if (!$stmt->execute($placeholders)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Inserts multiple records into the specified table using the Query class.
     * @param string $table The name of the table to insert into.
     * @param array $data An array of associative arrays, each representing a row to insert.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The ID of the last inserted row.
     */
    public function insertMany(string $table, array $data): int
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        if (empty($data) || !is_array($data[0])) {
            throw new InvalidArgumentException("Data must be a non-empty array of associative arrays.");
        }

        $fields = array_keys($data[0]);

        // Use the Query class to build the insert query
        $query = new Query([
            'method' => 'INSERT',
            'table' => $table,
            'fields' => $fields,
            'values_to_insert' => count($data)
        ]);

        $placeholders = [];
        foreach ($data as $i => $row) {
            foreach ($fields as $field) {
                $placeholders[":{$field}_{$i}"] = $row[$field];
            }
        }

        $stmt = $this->conn->prepare((string) $query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        if (!$stmt->execute($placeholders)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Updates records in the specified table using the Query class.
     * @param string $table The name of the table to update.
     * @param array $data An associative array of column names and values to update.
     * @param string $where The WHERE clause to specify which records to update.
     * @param array $joins Optional joins for the query.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The number of affected rows.
     */
    public function update($table, array $data, string $where, array $joins = []): int {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        if (empty($data) || !is_array($data)) {
            throw new InvalidArgumentException("Data must be a non-empty associative array.");
        }

        $fields = array_keys($data);

        // Use the Query class to build the update query
        $query = new Query([
            'method' => 'UPDATE',
            'table' => $table,
            'fields' => $fields,
            'where' => $where,
            'joins'=> $joins,
        ]);

        $placeholders = [];
        foreach ($data as $field => $value) {
            $placeholders[":{$field}"] = $value;
        }

        $stmt = $this->conn->prepare((string) $query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        if (!$stmt->execute($placeholders)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        return (int) $stmt->rowCount(); // Return the number of affected rows
    }

    /**
     * Deletes records from the specified table using the Query class.
     * @param string $table The name of the table to delete from.
     * @param array $data Optional data for the query.
     * @param string $where The WHERE clause to specify which records to delete.
     * @param string $orderBy Optional ORDER BY clause.
     * @param int $limit Optional limit for the deletion.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The number of affected rows.
     */
    public function delete(string $table, array $data = [], string $where, string $orderBy = "", int $limit = 0): int {
        if (!$this->conn) {
            throw new RuntimeException('Database connection is not set.');
        }

        if (empty($table) || empty($where)) {
            throw new InvalidArgumentException('Table and where clause are required.');
        }

        $query = new Query([
            'method'=> 'DELETE',
            'table' => $table,
            'where'=> $where,
            'order_by'=> $orderBy,
            'limit'=> $limit
        ]);

        $stmt = $this->conn->prepare((string) $query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        if (!$stmt->execute($data)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        return (int) $stmt->rowCount(); // Return the number of affected rows
    }

    /**
     * Deletes all records from the specified table using the Query class.
     * @param string $table The name of the table to delete from.
     * @param array $data Optional data for the query.
     * @param string $orderBy Optional ORDER BY clause.
     * @param int $limit Optional limit for the deletion.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The number of affected rows.
     */
    public function deleteAll(string $table, array $data = [], string $orderBy = "", int $limit = 0): int {
        if (!$this->conn) {
            throw new RuntimeException('Database connection is not set.');
        }

        if (empty($table)) {
            throw new InvalidArgumentException('Table is required.');
        }

        $query = new Query([
            'method'=> 'DELETE',
            'table' => $table,
            'order_by'=> $orderBy,
            'limit'=> $limit
        ]);

        $stmt = $this->conn->prepare((string) $query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        if (!$stmt->execute($data)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        return (int) $stmt->rowCount(); // Return the number of affected rows
    }

    /**
     * Counts the number of records in the specified table using the Query class.
     * @param string $table The name of the table to count records from.
     * @param array $params Optional parameters for the query.
     * @param string $where Optional WHERE clause to filter the count.
     * @param array $joins Optional joins for the query.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The count of records.
     */
    public function count(string $table, array $params = [], string $where = '', array $joins = []): int
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        $query = "SELECT COUNT(*) FROM {$table}";

        if (!empty($where)) {
            $query .= " WHERE {$where}";
        }

        if (!empty($joins)) {
            foreach ($joins as $join) {
                $query .= " {$join}";
            }
        }

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        if (!$stmt->execute($params)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        return (int) $stmt->fetchColumn(); // Return the count
    }

    /**
     * Executes a transaction with the provided callback.
     * @param callable $callback The callback function to execute within the transaction.
     * @throws RuntimeException if the connection is not set or the transaction fails.
     * @return mixed The result of the callback function.
     */
    public function executeTransaction(callable $callback): mixed
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        try {
            $this->conn->beginTransaction();
            $result = $callback($this);
            $this->conn->commit();
            return $result;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw new RuntimeException("Transaction failed: " . $e->getMessage());
        }
    }
}

class Mysql extends Database
{
    public function __construct($ppt)
    {
        parent::__construct($ppt);
        $this->connect($ppt);
    }

    protected function connect($ppt)
    {
        $servername = $ppt["serverName"] ?? $ppt["host"] ?? null;
        $username = $ppt["username"] ?? $ppt["user"] ?? '';
        $password = $ppt["password"] ?? '';
        $db = $ppt["DB"] ?? $ppt["dbname"] ?? null;
        $codification = $ppt["codification"] ?? 'utf8mb4';

        $dsn = "mysql:host=$servername";
        if (!empty($db)) {
            $dsn .= ";dbname=$db";
        }
        $dsn .= ";charset=$codification";

        try {
            $conn = new PDO($dsn, $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            parent::setConnection($conn);
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
}

class Postgres extends Database
{
    public function __construct($ppt)
    {
        parent::__construct($ppt);
        $this->connect($ppt);
    }

    protected function connect($ppt)
    {
        $servername = $ppt["serverName"] ?? $ppt["host"] ?? null;
        $username = $ppt["username"] ?? $ppt["user"] ?? '';
        $password = $ppt["password"] ?? '';
        $db = $ppt["DB"] ?? $ppt["dbname"] ?? null;
        $codification = $ppt["codification"] ?? 'utf8';

        $dsn = "pgsql:host=$servername";
        if (!empty($db)) {
            $dsn .= ";dbname=$db";
        }
        if (!empty($codification)) {
            $dsn .= ";options='--client_encoding=$codification'";
        }

        try {
            $conn = new PDO($dsn, $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            parent::setConnection($conn);
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
}

class Sqlite extends Database
{
    public function __construct($ppt)
    {
        parent::__construct($ppt);
        $this->connect($ppt);
    }

    protected function connect($ppt)
    {
        $dbFile = $ppt["DB"] ?? $ppt["dbname"] ?? null;

        if (empty($dbFile)) {
            throw new InvalidArgumentException("Database file is required for SQLite.");
        }

        $dsn = "sqlite:{$dbFile}";

        try {
            $conn = new PDO($dsn);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            parent::setConnection($conn);
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
}

class Sql extends Database
{
    public function __construct($ppt)
    {
        parent::__construct($ppt);
        $this->connect($ppt);
    }

    protected function connect($ppt)
    {
        $servername = $ppt["serverName"] ?? $ppt["host"] ?? null;
        $username = $ppt["username"] ?? $ppt["user"] ?? '';
        $password = $ppt["password"] ?? '';
        $db = $ppt["DB"] ?? $ppt["dbname"] ?? null;

        if (empty($servername) || empty($username) || empty($db)) {
            throw new InvalidArgumentException("Server name, username, and database name are required for SQL Server.");
        }

        $dsn = "sqlsrv:Server={$servername};Database={$db}";

        try {
            $conn = new PDO($dsn, $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            parent::setConnection($conn);
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
}

// Example object
$database = new Mysql(
    [
        'host' => 'localhost', 
        'user' =>'root',
        'password' => '',
        'DB' => 'users',
        'codification' => 'utf8mb4'
    ]
);

// Example usage
$query = new Query([
    'method' => 'SELECT',
    'fields' => ['id', 'name'],
    'table' => 'users',
    'where' => 'active = 1',
    'order_by' => 'name ASC',
    'limit' => 10
]);

try {
    $result = $database->select($query);
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

?>