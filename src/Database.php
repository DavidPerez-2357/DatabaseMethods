<?php
/**
 * Database.php
 *
 * Provides the Database base class with all common database operations:
 * querying, inserting, updating, deleting, counting, and transaction management.
 *
 * @author DavidPerez-2357
 * @link https://github.com/DavidPerez-2357/DatabaseMethods
 */

/**
 * Database class to handle database operations using PDO.
 * Provides methods for executing queries, inserting, updating, deleting, and selecting records.
 *
 * @package DatabaseMethods
 */
class Database
{
    private $properties; // Array with the initial properties of the class
    private $conn; // connection variable
    private $json_encode = false; // Default value for json_encode

    function __construct($ppt)
    {
        $this->properties = $ppt;
    }

    function __call($method, $args)
    {
        static $allowedMethods = ['select', 'selectone', 'insert', 'update', 'delete', 'deleteall', 'count'];
        if (!in_array(strtolower($method), $allowedMethods, true)) {
            throw new BadMethodCallException("Method '{$method}' does not exist in " . get_class($this) . ".");
        }

        // Replace keywords in every array argument before dispatching
        foreach ($args as $i => $arg) {
            if (is_array($arg)) {
                $args[$i] = $this->replaceKeywordsInData($arg);
            }
        }

        return call_user_func_array([$this, $method], $args);
    }

    protected function setConnection($conn)
    {
        $this->conn = $conn;
    }

    public function setJsonEncode($bool)
    {
        $this->json_encode = $bool;
    }

    /**
     * Replaces keywords in the data array with actual values.
     * This method is used to replace placeholders like @lastInsertId, @currentDate, and @currentDateTime.
     * Supports flat associative arrays and multi-row arrays (where every element is an associative array,
     * e.g., for insertMany). Arbitrary deeply nested structures are not recursed into.
     * @param mixed $data The data array containing the placeholders, or a non-array value (returned as-is).
     * @return mixed The modified data with placeholders replaced, or the original value if not an array.
     */
    function replaceKeywordsInData($data)
    {
        if (empty($data) || !is_array($data)) {
            return $data;
        }

        // Detect a true multi-row array: the array must be a sequential list (0-indexed numeric
        // keys) and every element must be an array. Requiring sequential numeric keys avoids a
        // false positive for associative single-row payloads whose every value happens to be an
        // array (e.g. a JSON/metadata column), which would otherwise route them down the
        // recursive branch and skip scalar keyword replacement.
        // array_is_list() is PHP 8.1+; use the equivalent keys check for PHP 5.4 compatibility.
        $isList = (array_keys($data) === range(0, count($data) - 1));
        $allArrays = true;
        foreach ($data as $value) {
            if (!is_array($value)) {
                $allArrays = false;
                break;
            }
        }
        if ($isList && $allArrays) {
            foreach ($data as $key => $row) {
                $data[$key] = $this->replaceKeywordsInData($row);
            }
            return $data;
        }

        $keywords = [
            // Date and time
            '@currentDate' => date('Y-m-d'),
            '@currentDateTime' => date('Y-m-d H:i:s'),
            '@currentTime' => date('H:i:s'),
            '@currentTimestamp' => time(),
            '@currentYear' => date('Y'),
            '@currentMonth' => date('m'),
            '@currentDay' => date('d'),
            '@currentWeekday' => date('l'),

            // Random values
            '@randomString' => substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8),
            '@randomInt' => rand(1, 9999),
            '@randomFloat' => rand(1, 9999) / 100,
            '@randomBoolean' => rand(0, 1) ? true : false,

            // Custom keywords can be added here
        ];

        // @lastInsertId is computed lazily: getLastInsertId() requires a connection and is
        // unnecessary when the keyword is not present in the data.
        if (in_array('@lastInsertId', $data, true)) {
            $keywords['@lastInsertId'] = $this->getLastInsertId();
        }

        // Replace values that are keywords like @lastInsertId, @currentDate, @currentDateTime
        foreach ($data as $key => $value) {
            if (is_string($value) && isset($keywords[$value])) {
                $data[$key] = $keywords[$value];
            }
        }

        return $data;
    }

    /**
     * Executes a plain SQL query.
     * @param string $query The SQL query to execute.
     * @param array $data Optional parameters for the query.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return bool True on success, false on failure.
     */
    public function executePlainQuery($query, $data = [])
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            // Use errorInfo for PDO
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        $result = $stmt->execute($data);

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        return true;
    }

    /**
     * Executes a plain SELECT SQL query and returns the results.
     * @param string $query The SQL SELECT query to execute.
     * @param array $data Optional parameters for the query.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return array|string The result set as an associative array, or a JSON-encoded string if json_encode is enabled.
     */
    public function plainSelect($query, $data = [])
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        $result = $stmt->execute($data);

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($this->json_encode) {
            $json = json_encode($results);
            return $json === false ? array() : $json;
        }
        return $results;
    }

    /**
     * Executes a SELECT query using the Query class and returns a single row.
     * @param Query $query The Query object containing the SQL query.
     * @param array $data Optional parameters for the query.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return array The result row as an associative array.
     */
    private function selectOne($query, $data = [])
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        $stmt = $this->conn->prepare((string) $query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        if (!$stmt->execute($data)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        // Fetch a single row as an associative array
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return $this->json_encode ? json_encode(array()) : array();
        }
        return $this->json_encode ? json_encode($row) : $row;
    }

    /**
     * Executes a SELECT query using the Query class and returns all results.
     * @param Query $query The Query object containing the SQL query.
     * @param array $data Optional parameters for the query.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return array The result set as an associative array.
     */
    private function select($query, $data = [])
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        $stmt = $this->conn->prepare((string) $query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        if (!$stmt->execute($data)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        // Fetch all results as an associative array
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($this->json_encode) {
            $json = json_encode($results);
            return $json === false ? array() : $json;
        }
        return $results;
    }

    /**
     * Inserts records into the specified table using the Query class.
     * This method detects if the data is a single record or multiple records and calls the appropriate method.
     * @param string $table The name of the table to insert into.
     * @param array $data An associative array of column names and values to insert, or an array of such arrays for multiple records.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The ID of the last inserted row or the number of affected rows for multiple inserts.
     */
    private function insert($table, $data)
    {
        // Detect if the data is a single record or multiple records
        if (isset($data[0]) && is_array($data[0])) {
            // Multiple records — keywords were already replaced by __call before dispatch.
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
    private function insertOne($table, $data)
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
            throw new RuntimeException("Query preparation failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        if (!$stmt->execute($placeholders)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
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
    private function insertMany($table, $data)
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        if (empty($data) || !isset($data[0]) || !is_array($data[0])) {
            throw new InvalidArgumentException("Data must be a non-empty array of associative arrays.");
        }

        // Validate that all rows are arrays and contain the required fields.
        $expectedFields = array_keys($data[0]);
        foreach ($data as $i => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("Data row at index {$i} must be an associative array.");
            }
            foreach ($expectedFields as $field) {
                if (!array_key_exists($field, $row)) {
                    throw new InvalidArgumentException("Data row at index {$i} is missing required field '{$field}'.");
                }
            }
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
            throw new RuntimeException("Query preparation failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        if (!$stmt->execute($placeholders)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Updates records in the specified table using the Query class.
     * @param string $table The name of the table to update.
     * @param array $data An associative array of column names and values to update.
     * @param string $where The WHERE clause to specify which records to update.
     * @param array $whereData Optional associative array of bindings for the WHERE clause.
     *                         Keys must not overlap with the column names in $data.
     *                         For backwards compatibility, a list-style (numerically-indexed) array
     *                         may be interpreted as $joins (the old 4th-parameter position) when its
     *                         values are compatible with JOIN clauses and the $where string has no
     *                         placeholders; otherwise, list-style arrays are rejected.
     * @param array $joins Optional joins for the query.
     * @throws InvalidArgumentException if $data is invalid or a binding key conflicts between $data and $whereData.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The number of affected rows.
     */
    private function update($table, $data, $where, $whereData = [], $joins = [])
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        if (empty($data) || !is_array($data)) {
            throw new InvalidArgumentException("Data must be a non-empty associative array.");
        }

        // Backwards compatibility: if $whereData is a list-style (numerically-indexed) array
        // whose values all look like JOIN clauses (strings containing 'JOIN', case-insensitive),
        // and the WHERE clause has no placeholders, and no explicit $joins was passed,
        // treat $whereData as $joins (old 4th-arg position).
        if (!empty($whereData) && empty($joins)) {
            // Consider any array whose keys are all integers as "numerically-indexed",
            // even if the numeric indices are non-sequential (e.g. [1 => 'LEFT JOIN ...']).
            $numericKeysOnly = true;
            foreach (array_keys($whereData) as $key) {
                if (!is_int($key)) {
                    $numericKeysOnly = false;
                    break;
                }
            }

            if ($numericKeysOnly) {
                $whereHasPlaceholders = is_string($where) && (
                    strpos($where, '?') !== false ||
                    preg_match('/:[A-Za-z_][A-Za-z0-9_]*/', $where) === 1
                );

                $allLookLikeJoins = true;
                foreach ($whereData as $v) {
                    if (!is_string($v) || stripos($v, 'join') === false) {
                        $allLookLikeJoins = false;
                        break;
                    }
                }

                if ($allLookLikeJoins && !$whereHasPlaceholders) {
                    // Reindex numerically-keyed array before using it as $joins.
                    $joins = array_values($whereData);
                    $whereData = [];
                }
            }
        }

        if (!is_array($whereData)) {
            throw new InvalidArgumentException("\$whereData must be an associative array of placeholder names to values.");
        }

        // Reject non-associative (list-style) arrays with numeric keys, as they cannot be
        // safely converted to named placeholders.
        if ($whereData !== [] && $whereData === array_values($whereData)) {
            throw new InvalidArgumentException("\$whereData must be an associative array with string keys; numeric or list-style arrays are not supported.");
        }

        // update() always generates named placeholders for SET; mixing positional '?' in $where
        // is not supported by PDO and will fail at execute time.
        if (is_string($where) && strpos($where, '?') !== false) {
            throw new InvalidArgumentException(
                "Positional placeholders ('?') are not supported in \$where for update(); " .
                "use named placeholders (e.g. 'id = :id') and pass their values via \$whereData."
            );
        }

        $fields = array_keys($data);

        // Use the Query class to build the update query
        $query = new Query([
            'method' => 'UPDATE',
            'table' => $table,
            'fields' => $fields,
            'where' => $where,
            'joins' => $joins,
        ]);

        $placeholders = [];
        foreach ($data as $field => $value) {
            $placeholders[":{$field}"] = $value;
        }

        // Merge WHERE clause bindings, detecting conflicts with SET bindings
        foreach ($whereData as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new InvalidArgumentException("\$whereData must use non-empty string keys for placeholders; invalid key encountered.");
            }

            $paramKey = ($key[0] === ':') ? $key : ":{$key}";
            if (array_key_exists($paramKey, $placeholders)) {
                throw new InvalidArgumentException(
                    "Binding key '{$paramKey}' is used in both \$data (SET) and \$whereData (WHERE). " .
                    "Use distinct placeholder names to avoid conflicts."
                );
            }
            $placeholders[$paramKey] = $value;
        }

        $stmt = $this->conn->prepare((string) $query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        if (!$stmt->execute($placeholders)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        return (int) $stmt->rowCount(); // Return the number of affected rows
    }

    /**
     * Deletes records from the specified table using the Query class.
     * @param string $table The name of the table to delete from.
     * @param string $where The WHERE clause to specify which records to delete.
     * @param array $whereData Optional bindings for the WHERE clause.
     *                         For named placeholders (e.g. `id = :id`), pass an associative array;
     *                         keys are normalized to include a leading `:` if absent.
     *                         For positional placeholders (e.g. `id = ?`), pass a list-style array.
     * @param string $orderBy Optional ORDER BY clause.
     * @param int $limit Optional limit for the deletion.
     * @throws InvalidArgumentException if $whereData is not an array or contains invalid named keys.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The number of affected rows.
     */
    private function delete($table, $where, $whereData = [], $orderBy = "", $limit = 0)
    {
        if (!$this->conn) {
            throw new RuntimeException('Database connection is not set.');
        }

        if (empty($table) || empty($where)) {
            throw new InvalidArgumentException('Table and where clause are required.');
        }

        if (!is_array($whereData)) {
            throw new InvalidArgumentException("\$whereData must be an array of bindings for the WHERE clause.");
        }

        $query = new Query([
            'method' => 'DELETE',
            'table' => $table,
            'where' => $where,
            'order_by' => $orderBy,
            'limit' => $limit
        ]);

        // For positional placeholders pass the array through unchanged.
        // For named placeholders normalize keys to include a leading ':'.
        if ($whereData !== [] && $whereData !== array_values($whereData)) {
            $placeholders = [];
            foreach ($whereData as $key => $value) {
                if (!is_string($key) || $key === '') {
                    throw new InvalidArgumentException("\$whereData must use non-empty string keys for named placeholders; invalid key encountered.");
                }
                $paramKey = ($key[0] === ':') ? $key : ":{$key}";
                if (array_key_exists($paramKey, $placeholders)) {
                    throw new InvalidArgumentException("Duplicate normalized placeholder key '{$paramKey}' in \$whereData; conflicting entries such as 'id' and ':id' are not allowed.");
                }
                $placeholders[$paramKey] = $value;
            }
        } else {
            $placeholders = $whereData;
        }

        $stmt = $this->conn->prepare((string) $query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        if (!$stmt->execute($placeholders)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        return (int) $stmt->rowCount(); // Return the number of affected rows
    }

    /**
     * Deletes all records from the specified table using the Query class.
     * @param string $table The name of the table to delete from.
     * @param array $data Optional parameters for the query.
     * @param string $orderBy Optional ORDER BY clause.
     * @param int $limit Optional limit for the deletion.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The number of affected rows.
     */
    private function deleteAll($table, $data = [], $orderBy = "", $limit = 0)
    {
        if (!$this->conn) {
            throw new RuntimeException('Database connection is not set.');
        }

        if (empty($table)) {
            throw new InvalidArgumentException('Table is required.');
        }

        $query = new Query([
            'method' => 'DELETE',
            'table' => $table,
            'order_by' => $orderBy,
            'limit' => $limit
        ]);

        $stmt = $this->conn->prepare((string) $query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        if (!$stmt->execute($data)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        return (int) $stmt->rowCount(); // Return the number of affected rows
    }

    /**
     * Counts the number of records in the specified table using the Query class.
     * @param string $table The name of the table to count records from.
     * @param array $data Optional parameters for the query.
     * @param string $where Optional WHERE clause to filter the count.
     * @param array $joins Optional joins for the query.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The count of records.
     */
    private function count($table, $data = [], $where = '', $joins = [])
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        $query = "SELECT COUNT(*) FROM {$table}";

        if (!empty($joins)) {
            foreach ($joins as $join) {
                $query .= " {$join}";
            }
        }

        if (!empty($where)) {
            $query .= " WHERE {$where}";
        }

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException("Query preparation failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        if (!$stmt->execute($data)) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException("Query execution failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
        }

        return (int) $stmt->fetchColumn(); // Return the count
    }

    /**
     * Executes a transaction with the provided callback.
     * @param callable $callback The callback function to execute within the transaction.
     * @throws RuntimeException if the connection is not set or the transaction fails.
     * @return mixed The result of the callback function.
     */
    public function executeTransaction($callback)
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

    /**
     * Gets the last inserted ID from the database.
     * @throws RuntimeException if the connection is not set.
     * @return int The last inserted ID.
     */
    public function getLastInsertId()
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }

        return (int) $this->conn->lastInsertId();
    }
}
