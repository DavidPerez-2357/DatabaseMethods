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
    private $keywordCheckEnabled = true; // Default value for keyword checking

    /**
     * Associative array of supported JOIN types for this driver.
     *
     * Maps a human-readable join name to the SQL keyword used in queries.
     * Child drivers may override this property to restrict or extend the
     * list of supported join types for the underlying SQL engine.
     *
     * Example entry: 'INNER' => 'INNER JOIN'
     *
     * @var array
     */
    protected $supportedJoins = array(
        'INNER' => 'INNER JOIN',
        'LEFT'  => 'LEFT JOIN',
        'RIGHT' => 'RIGHT JOIN',
        'FULL'  => 'FULL JOIN',
    );

    public function __construct($ppt)
    {
        $this->properties = $ppt;
    }

    public function __call($method, $args)
    {
        static $allowedMethods = ['select', 'selectone', 'insert', 'update', 'delete', 'deleteall', 'count'];
        static $canonicalNames = [
            'selectone' => 'selectOne',
            'deleteall' => 'deleteAll',
        ];
        $lower = strtolower($method);
        if (!in_array($lower, $allowedMethods, true)) {
            throw new BadMethodCallException("Method '{$method}' does not exist in " . get_class($this) . ".");
        }

        // Normalize to canonical camelCase method name
        $canonical = isset($canonicalNames[$lower]) ? $canonicalNames[$lower] : $lower;

        // Replace keywords in every array argument before dispatching
        if ($this->keywordCheckEnabled) {
            foreach ($args as $i => $arg) {
                if (is_array($arg)) {
                    $args[$i] = $this->replaceKeywordsInData($arg);
                }
            }
        }

        return call_user_func_array([$this, $canonical], $args);
    }

    protected function setConnection($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Returns the first non-null value found in $ppt for the given ordered list of $keys,
     * or $default when none of the keys are present.
     *
     * Canonical connection-config keys and their accepted aliases:
     *  - serverName  : host      — hostname or IP address of the database server
     *  - username    : user      — database user
     *  - DB          : dbname    — database/schema identifier for server-based drivers
     *                             and the SQLite database file path when using the
     *                             SQLite driver (used as dsn "sqlite:{DB}")
     *  - password    : (none)    — user password
     *  - codification: (none)    — character encoding (e.g. "utf8", "utf8mb4")
     *
     * @param array $ppt     Configuration array passed to the driver constructor.
     * @param array $keys    Ordered list of key names to try (first match wins).
     * @param mixed $default Value to return when none of the keys are present.
     * @return mixed
     */
    protected function getConfigValue(array $ppt, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (isset($ppt[$key])) {
                return $ppt[$key];
            }
        }
        return $default;
    }

    /**
     * Enables or disables JSON encoding of query results.
     *
     * When enabled, methods such as select(), selectOne(), and plainSelect()
     * will return a JSON-encoded string
     * instead of a PHP array.
     * If JSON encoding fails (e.g. the result contains invalid UTF-8), an
     * empty array is returned instead.
     *
     * @param bool $bool True to enable JSON encoding, false to disable.
     * @return $this
     */
    public function setJsonEncode($bool)
    {
        $this->json_encode = (bool) $bool;
        return $this;
    }

    /**
     * Enables or disables keyword replacement in query data arrays.
     *
     * When enabled (the default), special placeholder strings such as
     * @currentDate, @currentDateTime, @randomInt, and @lastInsertId are
     * automatically replaced with their actual values for operations routed
     * through the magic __call() method (select, insert, update, delete,
     * deleteAll, count, selectOne). Methods such as runPlainQuery() and
     * plainSelect() are not affected and never perform keyword replacement.
     * Set to false to pass data values through unmodified.
     *
     * @param bool $bool True to enable keyword checking, false to disable.
     * @return $this
     */
    public function enableKeywordCkeck($bool)
    {
        $this->keywordCheckEnabled = (bool) $bool;
        return $this;
    }

    /**
     * Returns the list of JOIN types supported by this driver.
     *
     * Each entry maps a human-readable join name to the SQL keyword.
     * Drivers that do not support certain join types (e.g. SQLite does not
     * support RIGHT JOIN or FULL JOIN) will return a restricted list.
     *
     * @return array Associative array of supported join types (e.g. ['INNER' => 'INNER JOIN', ...]).
     */
    public function getSupportedJoinTypes()
    {
        return $this->supportedJoins;
    }

    /**
     * Replaces keywords in the data array with actual values.
     * This method is used to replace placeholders like @lastInsertId, @currentDate, and @currentDateTime.
     * Supports flat associative arrays and multi-row arrays (where every element is an associative array,
     * e.g., for insertMany). Arbitrary deeply nested structures are not recursed into.
     * @param mixed $data The data array containing the placeholders, or a non-array value (returned as-is).
     * @return mixed The modified data with placeholders replaced, or the original value if not an array.
     */
    protected function replaceKeywordsInData($data)
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
     * Executes a plain SQL write statement and returns the affected row count.
     * Use plainSelect() to execute queries that return a result set.
     * @param string $query The SQL statement to execute.
     * @param array $data Optional parameters for the query.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The PDO-reported rowCount() (may be 0 for DDL statements).
     */
    public function runPlainQuery($query, $data = [])
    {
        $this->requireConnection();

        $stmt = $this->prepareAndExecute($query, $data);

        return $stmt->rowCount();
    }

    /**
     * Executes a plain SQL query and returns all result rows.
     * Use runPlainQuery() for write statements that do not return a result set.
     * @param string $query The SQL query to execute.
     * @param array $data Optional parameters for the query.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return array|string All rows as an associative array, or a JSON-encoded string when json_encode mode is enabled.
     */
    public function plainSelect($query, $data = [])
    {
        $this->requireConnection();

        $stmt = $this->prepareAndExecute($query, $data);

        return $this->formatResult($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Executes a SELECT query and returns a single row.
     * @param Query|string $query A Query object (limit(1) is applied automatically) or a raw SQL string.
     * @param array $data Optional parameters for the query.
     * @throws InvalidArgumentException if $query is neither a Query instance nor a string.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return array|string The result row as an associative array, or a JSON-encoded string when json_encode mode is enabled.
     */
    private function selectOne($query, $data = [])
    {
        if (!($query instanceof Query) && !is_string($query)) {
            throw new InvalidArgumentException(
                'selectOne() expects $query to be a Query instance or a string.'
            );
        }

        if ($query instanceof Query) {
            $query->limit(1);
        }
        $sql = (string) $query;

        $this->requireConnection();

        $stmt = $this->prepareAndExecute($sql, $data);

        // Fetch a single row as an associative array
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $this->formatResult($row === false ? array() : $row);
    }

    /**
     * Executes a SELECT query and returns all results.
     * @param Query|string $query A Query object or a raw SQL string.
     * @param array $data Optional parameters for the query.
     * @throws InvalidArgumentException if $query is neither a Query instance nor a string.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return array|string The result set as an associative array, or a JSON-encoded string if json_encode is enabled.
     */
    private function select($query, $data = [])
    {
        if (!($query instanceof Query) && !is_string($query)) {
            throw new InvalidArgumentException(
                'select() expects $query to be a Query instance or a string.'
            );
        }

        $sql = (string) $query;

        $this->requireConnection();

        $stmt = $this->prepareAndExecute($sql, $data);

        // Fetch all results as an associative array
        return $this->formatResult($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Inserts records into the specified table using the Query class.
     * This method detects if the data is a single record or multiple records and calls the appropriate method.
     * @param string $table The name of the table to insert into.
     * @param array $data An associative array of column names and values to insert,
     *                    or an array of such arrays for multiple records.
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
        $this->requireConnection();

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

        $this->prepareAndExecute((string) $query, $placeholders);
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
        $this->requireConnection();

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

        $this->prepareAndExecute((string) $query, $placeholders);
        return (int) $this->conn->lastInsertId();
    }

    /**
     * Prepares and executes a SQL statement with the given parameters.
     * PHP null values are explicitly bound as SQL NULL (PDO::PARAM_NULL) so that
     * they are stored as NULL rather than being cast to an empty string by some drivers.
     * For positional placeholders, bind values sequentially from 1..N to match
     * PDO execute([...]) semantics even when the input array has non-sequential
     * or non-zero-based numeric keys.
     * For named placeholders, normalize the key so that both ':name' and 'name'
     * forms work across all PDO drivers (add ':' prefix when missing).
     * Mixed positional and named keys are rejected to avoid silently mis-binding
     * parameters.
     * @param string $sql The SQL query to prepare and execute.
     * @param array $params Optional parameter bindings for the statement.
     * @throws InvalidArgumentException if $params is not an array or contains mixed/invalid keys.
     * @throws RuntimeException if preparation, binding, or execution fails.
     * @return PDOStatement The executed statement.
     */
    private function prepareAndExecute($sql, $params = [])
    {
        if (!is_array($params)) {
            throw new InvalidArgumentException('$params must be an array.');
        }

        $hasPositionalParams = false;
        $hasNamedParams      = false;
        foreach (array_keys($params) as $key) {
            if (is_int($key)) {
                $hasPositionalParams = true;
            } else {
                $hasNamedParams = true;
            }
        }

        if ($hasPositionalParams && $hasNamedParams) {
            throw new InvalidArgumentException('Mixed positional and named parameters are not supported.');
        }

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            $errorInfo = $this->conn->errorInfo();
            throw new RuntimeException(
                "Query preparation failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error')
            );
        }

        if ($hasPositionalParams) {
            $this->bindPositionalParams($stmt, $params);
        } else {
            $this->bindNamedParams($stmt, $params);
        }

        if (!$stmt->execute()) {
            $errorInfo = $stmt->errorInfo();
            throw new RuntimeException(
                "Query execution failed: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error')
            );
        }

        return $stmt;
    }

    /**
     * Binds positional (?) parameters sequentially from 1..N.
     * @param PDOStatement $stmt
     * @param array $params Values to bind (re-indexed from 0 via array_values).
     * @throws RuntimeException if any bindValue call fails.
     */
    private function bindPositionalParams($stmt, $params)
    {
        foreach (array_values($params) as $position => $value) {
            $this->bindOneValue($stmt, $position + 1, $value);
        }
    }

    /**
     * Binds named (:name) parameters, accepting both 'name' and ':name' key forms.
     * Keys are validated against /^:[A-Za-z_][A-Za-z0-9_]*$/ after normalization,
     * multiple leading colons are rejected, and duplicate normalized keys are rejected.
     * @param PDOStatement $stmt
     * @param array $params Associative array of placeholder names to values.
     * @throws InvalidArgumentException if any key is not a valid, unique named placeholder.
     * @throws RuntimeException if any bindValue call fails.
     */
    private function bindNamedParams($stmt, $params)
    {
        $seen = [];

        foreach ($params as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('Named parameter keys must be strings.');
            }

            if ($key === '') {
                throw new InvalidArgumentException('Named parameter keys must be non-empty strings.');
            }

            if (strlen($key) > 1 && $key[0] === ':' && $key[1] === ':') {
                throw new InvalidArgumentException('Named parameter keys may have at most one leading colon.');
            }

            $normalizedKey = ($key[0] === ':') ? $key : ':' . $key;
            if (!preg_match('/^:[A-Za-z_][A-Za-z0-9_]*$/', $normalizedKey)) {
                throw new InvalidArgumentException(
                    'Named parameter keys must match the format :[A-Za-z_][A-Za-z0-9_]*.'
                );
            }

            if (isset($seen[$normalizedKey])) {
                throw new InvalidArgumentException(
                    'Duplicate named parameter key after normalization: ' . $normalizedKey . '.'
                );
            }

            $seen[$normalizedKey] = true;
            $this->bindOneValue($stmt, $normalizedKey, $value);
        }
    }

    /**
     * Binds a single value to a statement placeholder, mapping PHP null to SQL NULL.
     * @param PDOStatement $stmt
     * @param int|string $param Placeholder index (1-based int) or name (':name').
     * @param mixed $value Value to bind.
     * @throws RuntimeException if bindValue fails.
     */
    private function bindOneValue($stmt, $param, $value)
    {
        if ($value === null) {
            $bound = $stmt->bindValue($param, null, PDO::PARAM_NULL);
        } else {
            $bound = $stmt->bindValue($param, $value);
        }

        if (!$bound) {
            $errorInfo    = $stmt->errorInfo();
            $driverDetail = isset($errorInfo[2]) && $errorInfo[2] !== '' ? ': ' . $errorInfo[2] : '';
            throw new RuntimeException("Parameter binding failed for placeholder " . $param . $driverDetail);
        }
    }

    /**
     * Normalizes an associative WHERE bindings array into PDO named-parameter form.
     * Adds a ':' prefix to keys that lack one, validates that each normalized key is a
     * legal PDO named-parameter name (matching /^:[A-Za-z_][A-Za-z0-9_]*$/), and detects
     * both intra-array duplicates and collisions against an already-built placeholder map.
     * @param array $whereData Associative array of placeholder names to values.
     * @param array $existingPlaceholders Already-built placeholder map to check for SET-vs-WHERE conflicts.
     * @throws InvalidArgumentException if a key is invalid, malformed, duplicated, or conflicts
     *                                   with $existingPlaceholders.
     * @return array Normalized placeholder array with ':'-prefixed keys.
     */
    private function normalizeNamedWhereBindings($whereData, $existingPlaceholders = [])
    {
        $result = [];

        foreach ($whereData as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new InvalidArgumentException(
                    "\$whereData must use non-empty string keys for placeholders; invalid key encountered."
                );
            }

            $paramKey = ($key[0] === ':') ? $key : ":{$key}";

            if (!preg_match('/^:[A-Za-z_][A-Za-z0-9_]*$/', $paramKey)) {
                throw new InvalidArgumentException(
                    "Invalid placeholder name '{$paramKey}' in \$whereData; " .
                    "placeholder names must start with a letter or underscore and contain only letters, " .
                    "digits, and underscores."
                );
            }

            if (array_key_exists($paramKey, $result)) {
                throw new InvalidArgumentException(
                    "Binding key '{$paramKey}' is duplicated within \$whereData (WHERE); " .
                    "each placeholder must be unique."
                );
            }

            if (!empty($existingPlaceholders) && array_key_exists($paramKey, $existingPlaceholders)) {
                throw new InvalidArgumentException(
                    "Binding key '{$paramKey}' is used in both \$data (SET) and \$whereData (WHERE). " .
                    "Use distinct placeholder names to avoid conflicts."
                );
            }

            $result[$paramKey] = $value;
        }

        return $result;
    }

    /**
     * Throws a RuntimeException if the database connection has not been established.
     * @throws RuntimeException
     */
    private function requireConnection()
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }
    }

    /**
     * Detects whether $whereData uses named or positional placeholders, normalizes
     * accordingly, and returns the resolved bindings array.
     * Named placeholders (any string key) are forwarded to normalizeNamedWhereBindings();
     * positional placeholders (all integer keys) are returned as a 0-indexed list.
     * @param array $whereData
     * @return array
     */
    private function resolveWhereBindings($whereData)
    {
        foreach ($whereData as $k => $_val) {
            if (!is_int($k)) {
                return $this->normalizeNamedWhereBindings($whereData);
            }
        }
        return array_values($whereData);
    }

    /**
     * Returns $result encoded as JSON when json_encode mode is enabled,
     * or returns the plain value otherwise.
     * On json_encode failure an empty array is returned.
     * @param mixed $result
     * @return mixed
     */
    private function formatResult($result)
    {
        if ($this->json_encode) {
            $json = json_encode($result);
            return $json === false ? array() : $json;
        }
        return $result;
    }

    /**
     * Updates records in the specified table using the Query class.
     * @param string $table The name of the table to update.
     * @param array $data An associative array of column names and values to update.
     * @param string $where The WHERE clause to specify which records to update.
     * @param array $whereData Optional associative array of bindings for the WHERE clause.
     *                         Keys must not overlap with the column names in $data.
     *                         Each key must be a valid PDO named-parameter name
     *                         (letters, digits, underscores; starting with a letter or underscore).
     * @param array $joins Optional joins for the query.
     * @throws InvalidArgumentException if $data or $whereData is invalid, or a binding key
     *                                   conflicts between $data and $whereData.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The number of affected rows.
     */
    private function update($table, $data, $where, $whereData = [], $joins = [])
    {
        $this->requireConnection();

        if (!is_array($whereData)) {
            throw new InvalidArgumentException(
                "\$whereData must be an associative array of placeholder names to values."
            );
        }

        if (empty($data) || !is_array($data)) {
            throw new InvalidArgumentException("Data must be a non-empty associative array.");
        }

        // $whereData must be associative (string keys only); numeric/list-style arrays are not supported.
        foreach (array_keys($whereData) as $k) {
            if (!is_string($k)) {
                throw new InvalidArgumentException(
                    "\$whereData must be an associative array with string keys; " .
                    "numeric or list-style arrays are not supported."
                );
            }
        }

        // update() always generates named SET placeholders; positional '?' in $where is not supported.
        if (is_string($where) && strpos($where, '?') !== false) {
            throw new InvalidArgumentException(
                "Positional placeholders ('?') are not supported in \$where for update(); " .
                "use named placeholders (e.g. 'id = :id') and pass their values via \$whereData."
            );
        }

        $query = new Query([
            'method' => 'UPDATE',
            'table' => $table,
            'fields' => array_keys($data),
            'where' => $where,
            'joins' => $joins,
        ]);

        $placeholders = [];
        foreach ($data as $field => $value) {
            $placeholders[":{$field}"] = $value;
        }

        $placeholders = array_merge(
            $placeholders,
            $this->normalizeNamedWhereBindings($whereData, $placeholders)
        );

        $stmt = $this->prepareAndExecute((string) $query, $placeholders);
        return (int) $stmt->rowCount();
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
        $this->requireConnection();

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

        $stmt = $this->prepareAndExecute((string) $query, $this->resolveWhereBindings($whereData));
        return (int) $stmt->rowCount();
    }

    /**
     * Deletes all records from the specified table using the Query class.
     * @param string $table The name of the table to delete from.
     * @param string $orderBy Optional ORDER BY clause.
     * @param int $limit Optional limit for the deletion.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The number of affected rows.
     */
    private function deleteAll($table, $orderBy = "", $limit = 0)
    {
        $this->requireConnection();

        if (empty($table)) {
            throw new InvalidArgumentException('Table is required.');
        }

        $query = new Query([
            'method' => 'DELETE',
            'table' => $table,
            'order_by' => $orderBy,
            'limit' => $limit
        ]);

        $stmt = $this->prepareAndExecute((string) $query, []);
        return (int) $stmt->rowCount();
    }

    /**
     * Counts the number of records in the specified table using the Query class.
     * @param string $table The name of the table to count records from.
     * @param string $where Optional WHERE clause to filter the count.
     * @param array $whereData Optional bindings for the WHERE clause.
     *                         For named placeholders (e.g. `active = :active`), pass an associative array;
     *                         keys are normalized to include a leading `:` if absent.
     *                         For positional placeholders (e.g. `active = ?`), pass a list-style array.
     * @param array $joins Optional joins for the query.
     * @throws InvalidArgumentException if $table is not a valid SQL identifier, or if $whereData is not an array or contains invalid named keys.
     * @throws RuntimeException if the connection is not set or the query execution fails.
     * @return int The count of records.
     */
    private function count($table, $where = '', $whereData = [], $joins = [])
    {
        $this->requireConnection();

        Query::validateIdentifier($table, 'table name');

        if (!is_array($whereData)) {
            throw new InvalidArgumentException("\$whereData must be an array of bindings for the WHERE clause.");
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

        $stmt = $this->prepareAndExecute($query, $this->resolveWhereBindings($whereData));
        return (int) $stmt->fetchColumn();
    }

    // =========================================================================
    // Helper methods — intention-revealing query shortcuts
    // =========================================================================

    /**
     * Validates a list of column names and returns a comma-separated SELECT list.
     * An empty array returns '*'. Each name is validated as a safe SQL identifier.
     *
     * @param array $columns List of column names (may be schema-qualified, e.g. 'users.id').
     * @throws InvalidArgumentException if any column name is not a valid SQL identifier.
     * @return string Comma-separated column list, or '*' for an empty array.
     */
    private function buildColumnList(array $columns)
    {
        if (empty($columns)) {
            return '*';
        }
        $validated = array();
        foreach ($columns as $col) {
            Query::validateIdentifier($col, 'column name');
            $validated[] = $col;
        }
        return implode(', ', $validated);
    }

    /**
     * Builds a parameterized equality WHERE clause from an associative conditions array.
     * Returns a two-element array: [whereClause string, params array].
     * An empty $conditions array returns ['', []].
     *
     * This is a private internal helper. The $paramPrefix value (if any) must consist solely
     * of ASCII letters and underscores so that the resulting placeholder names remain valid
     * PDO named-parameter identifiers. updateWhere() uses this to namespace SET vs WHERE
     * placeholders ('set_' and 'where_' respectively), preventing collisions for any valid
     * column name.
     *
     * @param array  $conditions  Associative array of unqualified-column-name => value pairs.
     *                            Keys must not contain dots (e.g. 'id', not 'users.id').
     * @param string $paramPrefix Optional prefix (letters and underscores only) prepended to
     *                            every placeholder name. Defaults to '' (no prefix).
     * @throws InvalidArgumentException if any column name is not a valid unqualified SQL identifier.
     * @return array Two-element list: [string whereClause, array params].
     */
    private function buildEqualityConditions(array $conditions, $paramPrefix = '')
    {
        $whereParts = array();
        $params = array();
        foreach ($conditions as $col => $value) {
            Query::validateUnqualifiedIdentifier($col, 'condition column');
            $placeholder = ':' . $paramPrefix . $col;
            $whereParts[] = "{$col} = {$placeholder}";
            $params[$placeholder] = $value;
        }
        return array(implode(' AND ', $whereParts), $params);
    }

    /**
     * Fetches rows from $table that match all $conditions, returning only the specified $columns.
     * Delegates to select() for connection management, execution, and result formatting.
     *
     * Generated SQL:
     *   SELECT col1, col2 FROM table WHERE cond1 = :cond1 AND cond2 = :cond2
     *
     * @param string $table      Table name (must be a valid SQL identifier).
     * @param array  $columns    Columns to return; empty array defaults to '*'.
     * @param array  $conditions Equality filters as unqualified-column-name => value pairs;
     *                           empty means no WHERE clause. Keys must not be schema-qualified
     *                           (e.g. use 'id', not 'users.id').
     * @throws InvalidArgumentException if $table or any identifier is invalid.
     * @throws RuntimeException         if the connection is not set or the query fails.
     * @return array|string All matching rows as an associative array, or a JSON string when
     *                      json_encode mode is enabled.
     */
    public function selectWhere($table, array $columns, array $conditions)
    {
        Query::validateIdentifier($table, 'table name');
        $columnList = $this->buildColumnList($columns);
        list($where, $params) = $this->buildEqualityConditions($conditions);

        $sql = "SELECT {$columnList} FROM {$table}";
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }

        return $this->select($sql, $params);
    }

    /**
     * Fetches a single row from $table that matches all $conditions, returning only the specified $columns.
     * Appends LIMIT 1 to the generated query.
     * Delegates to selectOne() for connection management, execution, and result formatting.
     *
     * Generated SQL:
     *   SELECT col1, col2 FROM table WHERE cond1 = :cond1 LIMIT 1
     *
     * @param string $table      Table name (must be a valid SQL identifier).
     * @param array  $columns    Columns to return; empty array defaults to '*'.
     * @param array  $conditions Equality filters as unqualified-column-name => value pairs;
     *                           empty means no WHERE clause. Keys must not be schema-qualified
     *                           (e.g. use 'id', not 'users.id').
     * @throws InvalidArgumentException if $table or any identifier is invalid.
     * @throws RuntimeException         if the connection is not set or the query fails.
     * @return array|string The matching row as an associative array, or an empty array when no row is
     *                      found. Returns a JSON string when json_encode mode is enabled.
     */
    public function selectOneWhere($table, array $columns, array $conditions)
    {
        Query::validateIdentifier($table, 'table name');
        $columnList = $this->buildColumnList($columns);
        list($where, $params) = $this->buildEqualityConditions($conditions);

        $sql = "SELECT {$columnList} FROM {$table}";
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }
        $sql .= " LIMIT 1";

        return $this->selectOne($sql, $params);
    }

    /**
     * Returns true if at least one row in $table matches all $conditions, false otherwise.
     * Executes SELECT 1 … LIMIT 1 for minimal overhead. Always returns bool regardless of
     * the json_encode setting.
     *
     * Generated SQL:
     *   SELECT 1 FROM table WHERE cond1 = :cond1 LIMIT 1
     *
     * @param string $table      Table name (must be a valid SQL identifier).
     * @param array  $conditions Equality filters as unqualified-column-name => value pairs.
     *                           Must not be empty. Keys must not be schema-qualified.
     * @throws InvalidArgumentException if $table or any identifier is invalid, or $conditions is empty.
     * @throws RuntimeException         if the connection is not set or the query fails.
     * @return bool
     */
    public function existsWhere($table, array $conditions)
    {
        $this->requireConnection();
        Query::validateIdentifier($table, 'table name');

        if (empty($conditions)) {
            throw new InvalidArgumentException('existsWhere() requires at least one condition.');
        }

        list($where, $params) = $this->buildEqualityConditions($conditions);

        $sql = "SELECT 1 FROM {$table} WHERE {$where} LIMIT 1";
        $stmt = $this->prepareAndExecute($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * Returns the number of rows in $table that match all $conditions.
     * Delegates to count() for connection management and execution.
     *
     * Generated SQL:
     *   SELECT COUNT(*) FROM table WHERE cond1 = :cond1
     *
     * @param string $table      Table name (must be a valid SQL identifier).
     * @param array  $conditions Equality filters as unqualified-column-name => value pairs;
     *                           empty means COUNT all rows. Keys must not be schema-qualified.
     * @throws InvalidArgumentException if $table or any identifier is invalid.
     * @throws RuntimeException         if the connection is not set or the query fails.
     * @return int
     */
    public function countWhere($table, array $conditions)
    {
        list($where, $params) = $this->buildEqualityConditions($conditions);
        return $this->count($table, $where, $params);
    }

    /**
     * Updates rows in $table that match all $conditions, setting the given $data values.
     * $conditions must not be empty to prevent accidental full-table updates.
     *
     * SET placeholders are named ':set_{col}' and WHERE placeholders ':where_{col}', so
     * no collision can occur for any valid column name combination.
     *
     * Generated SQL:
     *   UPDATE table SET col1 = :set_col1 WHERE cond1 = :where_cond1
     *
     * @param string $table      Table name (must be a valid SQL identifier).
     * @param array  $data       Associative array of unqualified-column-name => new value pairs.
     *                           Must not be empty. Keys must not be schema-qualified.
     * @param array  $conditions Equality filters as unqualified-column-name => value pairs.
     *                           Must not be empty. Keys must not be schema-qualified.
     * @throws InvalidArgumentException if $table or any identifier is invalid, or $data / $conditions
     *                                   is empty.
     * @throws RuntimeException         if the connection is not set or the query fails.
     * @return int Number of affected rows.
     */
    public function updateWhere($table, array $data, array $conditions)
    {
        $this->requireConnection();
        Query::validateIdentifier($table, 'table name');

        if (empty($data)) {
            throw new InvalidArgumentException(
                'updateWhere() requires at least one value to set in $data.'
            );
        }

        if (empty($conditions)) {
            throw new InvalidArgumentException(
                'updateWhere() requires at least one condition to prevent accidental full-table updates.'
            );
        }

        // SET clause: use ':set_{col}' placeholders to avoid any collision with WHERE params.
        $setParts = array();
        $params = array();
        foreach ($data as $col => $value) {
            Query::validateUnqualifiedIdentifier($col, 'data column');
            $setParts[] = "{$col} = :set_{$col}";
            $params[":set_{$col}"] = $value;
        }

        // WHERE clause: use ':where_{col}' placeholders. Since 'set_' ≠ 'where_', no collision
        // can occur for any valid column name combination.
        list($where, $whereParams) = $this->buildEqualityConditions($conditions, 'where_');
        $params = array_merge($params, $whereParams);

        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";
        $stmt = $this->prepareAndExecute($sql, $params);
        return (int) $stmt->rowCount();
    }

    /**
     * Deletes rows from $table that match all $conditions.
     * $conditions must not be empty to prevent accidental full-table deletes.
     * Delegates to delete() for connection management and execution.
     *
     * Generated SQL:
     *   DELETE FROM table WHERE cond1 = :cond1
     *
     * @param string $table      Table name (must be a valid SQL identifier).
     * @param array  $conditions Equality filters as unqualified-column-name => value pairs.
     *                           Must not be empty. Keys must not be schema-qualified.
     * @throws InvalidArgumentException if $table or any identifier is invalid, or $conditions is empty.
     * @throws RuntimeException         if the connection is not set or the query fails.
     * @return int Number of affected rows.
     */
    public function deleteWhere($table, array $conditions)
    {
        if (empty($conditions)) {
            throw new InvalidArgumentException(
                'deleteWhere() requires at least one condition to prevent accidental full-table deletes.'
            );
        }

        list($where, $params) = $this->buildEqualityConditions($conditions);
        return $this->delete($table, $where, $params);
    }

    /**
     * Fetches rows from $table where $column's value appears in the $values list.
     * Delegates to select() for connection management, execution, and result formatting.
     *
     * Generated SQL:
     *   SELECT col1, col2 FROM table WHERE column IN (:column_0, :column_1, :column_2)
     *
     * @param string $table   Table name (must be a valid SQL identifier).
     * @param array  $columns Columns to return; empty array defaults to '*'.
     * @param string $column  Column name to match against $values (must be a valid unqualified identifier;
     *                        no dots, e.g. 'id' not 'users.id').
     * @param array  $values  Non-empty list of values to match.
     * @throws InvalidArgumentException if $table, $column, or any column identifier is invalid,
     *                                   or $values is empty.
     * @throws RuntimeException         if the connection is not set or the query fails.
     * @return array|string All matching rows as an associative array, or a JSON string when
     *                      json_encode mode is enabled.
     */
    public function selectWhereIn($table, array $columns, $column, array $values)
    {
        Query::validateIdentifier($table, 'table name');
        Query::validateUnqualifiedIdentifier($column, 'IN column');

        if (empty($values)) {
            throw new InvalidArgumentException('selectWhereIn() requires a non-empty $values array.');
        }

        $columnList = $this->buildColumnList($columns);
        $placeholders = array();
        $params = array();
        foreach (array_values($values) as $i => $value) {
            $key = ":{$column}_{$i}";
            $placeholders[] = $key;
            $params[$key] = $value;
        }

        $sql = "SELECT {$columnList} FROM {$table}"
            . " WHERE {$column} IN (" . implode(', ', $placeholders) . ")";
        return $this->select($sql, $params);
    }

    /**
     * Fetches rows from $table matching all $conditions, ordered by the columns in $orderBy.
     * Delegates to select() for connection management, execution, and result formatting.
     *
     * Generated SQL:
     *   SELECT col1, col2 FROM table WHERE cond1 = :cond1 ORDER BY col3 DESC
     *
     * @param string $table      Table name (must be a valid SQL identifier).
     * @param array  $columns    Columns to return; empty array defaults to '*'.
     * @param array  $conditions Equality filters as unqualified-column-name => value pairs;
     *                           empty means no WHERE clause. Keys must not be schema-qualified.
     * @param array  $orderBy    Associative array of column => direction pairs (e.g. ['created_at' => 'DESC']).
     *                           Direction must be 'ASC' or 'DESC' (case-insensitive). An empty array omits
     *                           the ORDER BY clause.
     * @throws InvalidArgumentException if any identifier or direction value is invalid.
     * @throws RuntimeException         if the connection is not set or the query fails.
     * @return array|string All matching rows as an associative array, or a JSON string when
     *                      json_encode mode is enabled.
     */
    public function selectOrderedWhere($table, array $columns, array $conditions, array $orderBy)
    {
        Query::validateIdentifier($table, 'table name');
        $columnList = $this->buildColumnList($columns);
        list($where, $params) = $this->buildEqualityConditions($conditions);

        // Build ORDER BY clause
        $orderParts = array();
        foreach ($orderBy as $col => $dir) {
            Query::validateIdentifier($col, 'ORDER BY column');
            $dirUpper = strtoupper(trim((string) $dir));
            if ($dirUpper !== 'ASC' && $dirUpper !== 'DESC') {
                throw new InvalidArgumentException(
                    "Invalid ORDER BY direction '{$dir}' for column '{$col}'; must be 'ASC' or 'DESC'."
                );
            }
            $orderParts[] = "{$col} {$dirUpper}";
        }

        $sql = "SELECT {$columnList} FROM {$table}";
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }
        if (!empty($orderParts)) {
            $sql .= " ORDER BY " . implode(', ', $orderParts);
        }

        return $this->select($sql, $params);
    }

    /**
     * Fetches a paginated set of rows from $table matching all $conditions.
     * Delegates to select() for connection management, execution, and result formatting.
     *
     * Generated SQL:
     *   SELECT col1, col2 FROM table WHERE cond1 = :cond1 LIMIT 20 OFFSET 0
     *
     * @param string $table      Table name (must be a valid SQL identifier).
     * @param array  $columns    Columns to return; empty array defaults to '*'.
     * @param array  $conditions Equality filters as unqualified-column-name => value pairs;
     *                           empty means no WHERE clause. Keys must not be schema-qualified.
     * @param int    $limit      Maximum number of rows to return (must be a positive integer).
     * @param int    $offset     Number of rows to skip (must be a non-negative integer; defaults to 0).
     * @throws InvalidArgumentException if any identifier is invalid, or $limit / $offset are out of range.
     * @throws RuntimeException         if the connection is not set or the query fails.
     * @return array|string Paginated rows as an associative array, or a JSON string when
     *                      json_encode mode is enabled.
     */
    public function paginateWhere($table, array $columns, array $conditions, $limit, $offset = 0)
    {
        if (filter_var($limit, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1))) === false) {
            throw new InvalidArgumentException(
                'paginateWhere() expects $limit to be a positive integer.'
            );
        }
        if (filter_var($offset, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0))) === false) {
            throw new InvalidArgumentException(
                'paginateWhere() expects $offset to be a non-negative integer.'
            );
        }

        Query::validateIdentifier($table, 'table name');
        $columnList = $this->buildColumnList($columns);
        list($where, $params) = $this->buildEqualityConditions($conditions);

        $sql = "SELECT {$columnList} FROM {$table}";
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }
        $sql .= " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

        return $this->select($sql, $params);
    }

    // =========================================================================
    // Transaction
    // =========================================================================

    /**
     * Executes a transaction with the provided callback.
     * @param callable $callback The callback function to execute within the transaction.
     * @throws RuntimeException if the connection is not set or the transaction fails.
     * @return mixed The result of the callback function.
     */
    public function executeTransaction($callback)
    {
        $this->requireConnection();

        try {
            $this->conn->beginTransaction();
            $result = $callback($this);
            $this->conn->commit();
            return $result;
        } catch (Exception $e) {
            try {
                if ($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
            } catch (Exception $rollbackEx) {
                // Ignore rollback errors to preserve the original exception
            }
            throw new RuntimeException("Transaction failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Gets the last inserted ID from the database.
     * @throws RuntimeException if the connection is not set.
     * @return int The last inserted ID.
     */
    public function getLastInsertId()
    {
        $this->requireConnection();

        return (int) $this->conn->lastInsertId();
    }
}
