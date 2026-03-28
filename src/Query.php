<?php
/**
 * Query.php
 *
 * Provides the Query class for building SQL queries from a data array.
 * Supports SELECT, INSERT (PDO-style), UPDATE (PDO-style), and DELETE methods.
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
    private $data;
    private $query;

    public function __construct($queryData)
    {
        $this->data = $queryData;
        $this->query = $this->buildQuery();
    }

    public function __toString()
    {
        return $this->query;
    }

    public function buildQuery()
    {
        if (empty($this->data['method'])) {
            throw new InvalidArgumentException("Query method is required.");
        }

        switch (strtoupper($this->data['method'])) {
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
     * ]);
     *
     */
    public function buildSelectQuery()
    {
        if (!isset($this->data['method']) || strtoupper($this->data['method']) !== 'SELECT') {
            throw new InvalidArgumentException("Only SELECT queries are supported.");
        }

        $fields = isset($this->data['fields']) ? implode(", ", $this->data['fields']) : "*";
        if (!isset($this->data['table'])) {
            throw new InvalidArgumentException("Table is required.");
        }
        $table = $this->data['table'];

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
     * ]);
     * */
    public function buildPDOInsertQuery()
    {
        if (!isset($this->data['method']) || strtoupper($this->data['method']) !== 'INSERT') {
            throw new InvalidArgumentException("Only INSERT method is supported.");
        }

        if (!isset($this->data['table'])) {
            throw new InvalidArgumentException("Table is required.");
        }
        $table = $this->data['table'];

        if (!isset($this->data['fields'])) {
            throw new InvalidArgumentException("Fields are required.");
        }
        $fields = $this->data['fields'];

        $values = isset($this->data['values_to_insert']) ? $this->data['values_to_insert'] : 1;

        if (!is_array($fields) || empty($fields)) {
            throw new InvalidArgumentException("Fields must be a non-empty array.");
        }

        // Prepare placeholders for the query
        $placeholders = [];
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
     * @return string The constructed SQL UPDATE query and parameters.
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
    public function buildPDOUpdateQuery()
    {
        if (!isset($this->data['method']) || strtoupper($this->data['method']) !== 'UPDATE') {
            throw new InvalidArgumentException("Only UPDATE method is supported.");
        }

        if (!isset($this->data['table'])) {
            throw new InvalidArgumentException("Table is required.");
        }
        $table = $this->data['table'];

        if (!isset($this->data['fields'])) {
            throw new InvalidArgumentException("Fields are required.");
        }
        $fields = $this->data['fields'];

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
    public function buildDeleteQuery()
    {
        if (!isset($this->data['method']) || strtoupper($this->data['method']) !== 'DELETE') {
            throw new InvalidArgumentException("Only DELETE method is supported.");
        }

        if (!isset($this->data['table'])) {
            throw new InvalidArgumentException("Table is required.");
        }
        $table = $this->data['table'];

        $sql = "DELETE FROM {$table}";

        // Where
        if (!empty($this->data['where'])) {
            $sql .= " WHERE {$this->data['where']}";
        }

        if (!empty($this->data["order_by"])) {
            $sql .= " ORDER BY {$this->data['order_by']}";
        }

        if (!empty($this->data["limit"])) {
            $limit = (int) $this->data["limit"];
            if ($limit > 0) {
                $sql .= " LIMIT {$limit}";
            }
        }

        return $sql;
    }
}
