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
    private $properties;
    private $conn = null;
    private $json_encode = false;

    /**
     * Associative array of supported JOIN types for this driver.
     *
     * Maps a human-readable join name to the SQL keyword used in queries.
     * Child drivers may override this property to restrict or extend the
     * list of supported join types for the underlying SQL engine.
     *
     * Example entry: 'INNER' => 'INNER JOIN'
     *
     * @var array<string, string>
     */
    protected $supportedJoins = array(
        'INNER' => 'INNER JOIN',
        'LEFT'  => 'LEFT JOIN',
        'RIGHT' => 'RIGHT JOIN',
        'FULL'  => 'FULL JOIN',
    );

    public function __construct(array $ppt)
    {
        $this->properties = $ppt;
    }

    protected function setConnection(PDO $conn)
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
     * Enables or disables JSON encoding of results.
     * When enabled, `select`, `selectOne`, and `plainSelect` return a JSON string instead of a PHP array.
     *
     * @return static Fluent interface — returns the instance for chaining.
     */
    public function setJsonEncode($bool)
    {
        $this->json_encode = $bool;
        return $this;
    }

    /**
     * Returns the list of JOIN types supported by this driver.
     *
     * @return array Associative array of supported join types (e.g. ['INNER' => 'INNER JOIN', ...]).
     */
    public function getSupportedJoinTypes()
    {
        return $this->supportedJoins;
    }

    /**
     * Replaces keywords in the data array with actual values.
     * Supports flat associative arrays and multi-row arrays (where every element is an associative array,
     * e.g., for insertMany). Arbitrary deeply nested structures are not recursed into.
     */
    protected function replaceKeywordsInData(array $data)
    {
        if (empty($data)) {
            return $data;
        }

        // Detect a true multi-row array: non-empty sequential list where the first element is an array.
        // array_values() + array_keys() check is PHP 5.4+ compatible (replaces array_is_list() from PHP 8.1).
        $values = array_values($data);
        if ($values === $data && is_array($data[0])) {
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
     * Executes any SQL statement directly (INSERT, UPDATE, DELETE, DDL, etc.).
     * Returns `true` on success or throws on error.
     */
    public function executePlainQuery($query, array $data = [])
    {
        $this->requireConnection();

        $this->prepareAndExecute($query, $data);
        return true;
    }

    /**
     * Executes a raw SELECT SQL string and returns all rows.
     * Results are returned as an associative array, or a JSON string when json-encode mode is on.
     */
    public function plainSelect($query, array $data = [])
    {
        $this->requireConnection();

        $stmt = $this->prepareAndExecute($query, $data);

        return $this->formatResult($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Executes a SELECT query and returns a single row (the first match).
     *
     * `$query` can be a `Query` object or a raw SQL string.
     * Returns an empty array when no row matches.
     * Returns a JSON string instead of an array when json-encode mode is on.
     */
    public function selectOne($query, array $data = [])
    {
        $this->requireConnection();

        $data = $this->replaceKeywordsInData($data);
        if ($query instanceof Query) {
            $query->limit(1);
        }
        $stmt = $this->prepareAndExecute((string) $query, $data);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return $this->json_encode ? json_encode([]) : [];
        }
        return $this->json_encode ? json_encode($row) : $row;
    }

    /**
     * Executes a SELECT query and returns all matching rows.
     *
     * `$query` can be a `Query` object or a raw SQL string.
     * Returns an array of associative arrays, or a JSON string when json-encode mode is on.
     */
    public function select($query, array $data = [])
    {
        $this->requireConnection();

        $data = $this->replaceKeywordsInData($data);
        $stmt = $this->prepareAndExecute((string) $query, $data);

        return $this->formatResult($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Inserts one or more records into `$table`.
     *
     * Pass an associative array for a single row, or an array of associative
     * arrays to insert multiple rows in one statement.
     * Returns the last auto-increment ID inserted.
     */
    public function insert($table, array $data)
    {
        if (isset($data[0]) && is_array($data[0])) {
            $data = $this->replaceKeywordsInData($data);
            return $this->insertMany($table, $data);
        }
        $data = $this->replaceKeywordsInData($data);
        return $this->insertOne($table, $data);
    }

    private function insertOne($table, array $data)
    {
        $this->requireConnection();

        $fields = array_keys($data);

        $query = new Query([
            'method' => 'INSERT',
            'table' => $table,
            'fields' => $fields,
            'values_to_insert' => 1,
        ]);

        $placeholders = [];
        foreach ($fields as $field) {
            $placeholders[":{$field}_0"] = $data[$field];
        }

        $this->prepareAndExecute((string) $query, $placeholders);
        return (int) $this->conn->lastInsertId();
    }

    private function insertMany($table, array $data)
    {
        $this->requireConnection();

        if (empty($data) || !is_array($data[0])) {
            throw new InvalidArgumentException("Data must be a non-empty array of associative arrays.");
        }

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

        $query = new Query([
            'method' => 'INSERT',
            'table' => $table,
            'fields' => $fields,
            'values_to_insert' => count($data),
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
    private function prepareAndExecute($sql, array $params = [])
    {
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

    private function bindPositionalParams(PDOStatement $stmt, array $params)
    {
        foreach (array_values($params) as $position => $value) {
            $this->bindOneValue($stmt, $position + 1, $value);
        }
    }

    private function bindNamedParams(PDOStatement $stmt, array $params)
    {
        $seen = [];

        foreach ($params as $key => $value) {
            if (!is_string($key) || $key === '') {
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

    private function bindOneValue(PDOStatement $stmt, $param, $value)
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
     * Normalizes a WHERE bindings array into PDO named-parameter form.
     * Adds a ':' prefix to keys that lack one, validates each normalized key, and detects duplicates.
     */
    private function normalizeNamedWhereBindings(array $whereData)
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

            $result[$paramKey] = $value;
        }

        return $result;
    }

    private function requireConnection()
    {
        if (!$this->conn) {
            throw new RuntimeException("Database connection is not set.");
        }
    }

    private function resolveWhereBindings(array $whereData)
    {
        foreach ($whereData as $k => $_val) {
            if (!is_int($k)) {
                return $this->normalizeNamedWhereBindings($whereData);
            }
        }
        return array_values($whereData);
    }

    private function formatResult(array $result)
    {
        if ($this->json_encode) {
            $json = json_encode($result);
            return $json === false ? [] : $json;
        }
        return $result;
    }

    /**
     * Updates records in `$table`.
     *
     * `$data` is an associative array of column → value pairs to SET.
     * `$where` is a raw SQL WHERE fragment (use named placeholders, e.g. `id = :id`).
     * `$whereData` holds the bindings for the WHERE clause. The same column name
     * may appear in both `$data` and `$whereData` without any conflict — SET
     * bindings are internally distinguished with a `set_` prefix.
     *
     * Returns the number of affected rows.
     */
    public function update($table, array $data, $where, array $whereData = [], array $joins = [])
    {
        $this->requireConnection();

        if (empty($data)) {
            throw new InvalidArgumentException("Data must be a non-empty associative array.");
        }

        if (strpos($where, '?') !== false) {
            throw new InvalidArgumentException(
                "Positional placeholders ('?') are not supported in \$where for update(); " .
                "use named placeholders (e.g. 'id = :id') and pass their values via \$whereData."
            );
        }

        $data      = $this->replaceKeywordsInData($data);
        $whereData = $this->replaceKeywordsInData($whereData);

        // Validate table and join the table name.
        Query::validateIdentifier($table, 'table name');

        // Build SET clause using a 'set_' prefix on placeholders to avoid
        // any collision with WHERE bindings when the same column appears in both.
        $setClauses   = array();
        $placeholders = array();
        $setKeys      = array();
        foreach ($data as $field => $value) {
            if (!is_string($field)) {
                throw new InvalidArgumentException(
                    "update() \$data must use string column names as keys; integer key {$field} given."
                );
            }
            Query::validateUnqualifiedIdentifier($field, 'UPDATE field');
            $setClauses[]                  = "{$field} = :set_{$field}";
            $placeholders[":set_{$field}"] = $value;
            $setKeys[":set_{$field}"]      = true;
        }

        // Detect collision: a WHERE binding like ['set_active' => 1] would overwrite
        // the generated ':set_active' placeholder for a SET field named 'active'.
        $normalizedWhere = $this->normalizeNamedWhereBindings($whereData);
        foreach (array_keys($normalizedWhere) as $whereKey) {
            if (isset($setKeys[$whereKey])) {
                throw new InvalidArgumentException(
                    "WHERE binding key '{$whereKey}' collides with a generated SET placeholder. " .
                    "Rename the WHERE placeholder to avoid the conflict."
                );
            }
        }

        $sql = "UPDATE {$table}";
        if (!empty($joins)) {
            foreach ($joins as $join) {
                $sql .= " {$join}";
            }
        }
        $sql .= " SET " . implode(', ', $setClauses);
        $sql .= " WHERE {$where}";

        $placeholders = array_merge($placeholders, $normalizedWhere);

        $stmt = $this->prepareAndExecute($sql, $placeholders);
        return (int) $stmt->rowCount();
    }

    /**
     * Deletes records matching `$where` from `$table`.
     * Returns the number of affected rows.
     */
    public function delete($table, $where, array $whereData = [], $orderBy = '', $limit = 0)
    {
        $this->requireConnection();

        $whereData = $this->replaceKeywordsInData($whereData);

        $query = new Query([
            'method'   => 'DELETE',
            'table'    => $table,
            'where'    => $where,
            'order_by' => $orderBy,
            'limit'    => $limit,
        ]);

        $stmt = $this->prepareAndExecute((string) $query, $this->resolveWhereBindings($whereData));
        return (int) $stmt->rowCount();
    }

    /**
     * Deletes all records from `$table` (no WHERE clause).
     * Returns the number of affected rows.
     */
    public function deleteAll($table, $orderBy = '', $limit = 0)
    {
        $this->requireConnection();

        $query = new Query([
            'method'   => 'DELETE',
            'table'    => $table,
            'order_by' => $orderBy,
            'limit'    => $limit,
        ]);

        $stmt = $this->prepareAndExecute((string) $query, []);
        return (int) $stmt->rowCount();
    }

    /**
     * Returns the number of records in `$table` that match `$where`.
     * Omit `$where` to count all rows. Both named and positional placeholders are supported.
     */
    public function count($table, $where = '', array $whereData = [], array $joins = [])
    {
        $this->requireConnection();

        $whereData = $this->replaceKeywordsInData($whereData);

        // Use Query to validate the table name and build any JOINs safely.
        $q = Query::select(['COUNT(*)'])->from($table);
        foreach ($joins as $join) {
            $q->join($join);
        }
        if ($where !== '') {
            $q->where($where);
        }

        $stmt = $this->prepareAndExecute((string) $q, $this->resolveWhereBindings($whereData));
        return (int) $stmt->fetchColumn();
    }

    /**
     * Runs `$callback` inside a database transaction.
     * Commits on success; rolls back and re-throws on any exception.
     * Returns the value returned by `$callback`.
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
                // Ignore rollback errors to preserve the original exception.
            }
            throw new RuntimeException("Transaction failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Returns the last auto-increment ID inserted by this connection.
     */
    public function getLastInsertId()
    {
        $this->requireConnection();

        return (int) $this->conn->lastInsertId();
    }
}
