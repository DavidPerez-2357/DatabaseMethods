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
 * Queries can be built either by passing a full configuration array to the
 * constructor, or via the static factory methods combined with the fluent
 * setter API:
 *
 * ```php
 * // Array constructor (original API — still fully supported)
 * $query = new Query(['method' => 'SELECT', 'fields' => ['id'], 'table' => 'users']);
 *
 * // Fluent API
 * $query = Query::select(['id', 'name'])->from('users')->where('active = 1');
 * $query = Query::insert('users', ['name', 'email'])->valuesCount(3);
 * $query = Query::update('users', ['name', 'email'])->where('id = :id');
 * $query = Query::delete('users')->where('id = :id')->limit(10);
 * ```
 *
 * @package DatabaseMethods
 */
class Query
{
    private $data;
    private $query;

    /**
     * Creates a Query instance.
     *
     * When $queryData is a non-empty array the query is built immediately
     * (original behavior). When called with an empty array — as the static
     * factory methods do — building is deferred until the query string is
     * first needed.
     *
     * @param array $queryData Configuration array (optional when using factory methods).
     * @throws InvalidArgumentException if $queryData is not an array.
     */
    public function __construct($queryData = [])
    {
        if (!is_array($queryData)) {
            throw new InvalidArgumentException('Query constructor expects an array.');
        }
        $this->data = $queryData;
        if (!empty($queryData)) {
            $this->query = $this->buildQuery();
        }
    }

    public function __toString()
    {
        try {
            if ($this->query === null) {
                $this->query = $this->buildQuery();
            }
            return (string) $this->query;
        } catch (Exception $e) {
            // Throwing from __toString() is fatal in PHP 5.4–6.x, so we emit a warning instead.
            // Note: `Throwable` (which also covers `Error`) was introduced in PHP 7.0 and cannot
            // be used here without breaking the library's PHP 5.4+ compatibility guarantee.
            // In practice, buildQuery() only throws InvalidArgumentException (extends Exception),
            // so this catch is sufficient for all real-world error paths.
            trigger_error(
                'Error building SQL query in ' . __METHOD__ . ': ' . $e->getMessage(),
                E_USER_WARNING
            );
            return '';
        }
    }

    /**
     * Returns the built SQL query string.
     *
     * Unlike casting to a string, this method lets any exception thrown
     * during query building propagate to the caller.
     *
     * @throws InvalidArgumentException if the query configuration is invalid.
     * @return string
     */
    public function getQuery()
    {
        if ($this->query === null) {
            $this->query = $this->buildQuery();
        }
        return $this->query;
    }

    // -------------------------------------------------------------------------
    // Static factory methods
    // -------------------------------------------------------------------------

    /**
     * Creates a SELECT Query for the given fields.
     *
     * @param array|string|null $fields Column list. When omitted, null, or an empty array,
     *                                  defaults to ['*']. A string is normalized to a
     *                                  single-element array. An empty/whitespace-only string
     *                                  throws InvalidArgumentException.
     * @return static
     * @throws InvalidArgumentException If $fields is an empty/whitespace-only string, or
     *                                  not an array, string, or null.
     * @example
     * ```php
     * $query = Query::select(['id', 'name'])->from('users')->where('active = 1');
     * $query = Query::select('id')->from('users');
     * ```
     */
    public static function select($fields = [])
    {
        $instance = new static();
        $instance->data['method'] = 'SELECT';

        if ($fields === [] || $fields === null) {
            $instance->data['fields'] = ['*'];
        } elseif (is_string($fields)) {
            if (trim($fields) === '') {
                throw new InvalidArgumentException(
                    'Query::select() expects $fields to be a non-empty string, an array (empty defaults to [\'*\']), or omitted.'
                );
            }
            $instance->data['fields'] = [$fields];
        } elseif (is_array($fields)) {
            $instance->data['fields'] = $fields;
        } else {
            throw new InvalidArgumentException(
                'Query::select() expects $fields to be an array, string, or empty.'
            );
        }

        return $instance;
    }

    /**
     * Creates an INSERT Query for the given table and fields.
     *
     * `$fields` is optional here; you can also call `->fields([...])` in the chain.
     * The `fields` must be provided (either here or via `->fields()`) before the
     * query string is generated.
     *
     * @param string       $table  Target table name.
     * @param array|string $fields Columns to insert (optional; can be set later with ->fields()).
     *                             A string is normalized to a single-element array.
     * @return static
     * @throws InvalidArgumentException If $fields is not an array or string.
     * @example
     * ```php
     * $query = Query::insert('users', ['name', 'email'])->valuesCount(3);
     * // or
     * $query = Query::insert('users')->fields(['name', 'email'])->valuesCount(3);
     * ```
     */
    public static function insert($table, $fields = [])
    {
        $instance = new static();
        $instance->data['method'] = 'INSERT';
        $instance->data['table'] = $table;
        if (!empty($fields)) {
            if (is_string($fields)) {
                $instance->data['fields'] = [$fields];
            } elseif (is_array($fields)) {
                $instance->data['fields'] = $fields;
            } else {
                throw new InvalidArgumentException(
                    'Query::insert() expects $fields to be an array or string.'
                );
            }
        }
        return $instance;
    }

    /**
     * Creates an UPDATE Query for the given table and fields.
     *
     * `$fields` is optional here; you can also call `->fields([...])` in the chain.
     * The `fields` must be provided (either here or via `->fields()`) before the
     * query string is generated.
     *
     * @param string       $table  Target table name.
     * @param array|string $fields Columns to update (optional; can be set later with ->fields()).
     *                             A string is normalized to a single-element array.
     * @return static
     * @throws InvalidArgumentException If $fields is not an array or string.
     * @example
     * ```php
     * $query = Query::update('users', ['name', 'email'])->where('id = :id');
     * // or
     * $query = Query::update('users')->fields(['name', 'email'])->where('id = :id');
     * ```
     */
    public static function update($table, $fields = [])
    {
        $instance = new static();
        $instance->data['method'] = 'UPDATE';
        $instance->data['table'] = $table;
        if (!empty($fields)) {
            if (is_string($fields)) {
                $instance->data['fields'] = [$fields];
            } elseif (is_array($fields)) {
                $instance->data['fields'] = $fields;
            } else {
                throw new InvalidArgumentException(
                    'Query::update() expects $fields to be an array or string.'
                );
            }
        }
        return $instance;
    }

    /**
     * Creates a DELETE Query for the given table.
     *
     * @param string $table Target table name.
     * @return static
     * @example
     * ```php
     * $query = Query::delete('users')->where('id = :id')->limit(10);
     * ```
     */
    public static function delete($table)
    {
        $instance = new static();
        $instance->data['method'] = 'DELETE';
        $instance->data['table'] = $table;
        return $instance;
    }

    // -------------------------------------------------------------------------
    // Fluent setter methods
    // -------------------------------------------------------------------------

    /**
     * Sets the target table (alias of table()).
     *
     * @param string $table Table name.
     * @return $this
     */
    public function from($table)
    {
        $this->data['table'] = $table;
        $this->query = null;
        return $this;
    }

    /**
     * Sets the target table (alias of from()).
     *
     * @param string $table Table name.
     * @return $this
     */
    public function table($table)
    {
        return $this->from($table);
    }

    /**
     * Sets the column list.
     *
     * A string is normalized to a single-element array. For SELECT queries an empty
     * array defaults to ['*']. For INSERT and UPDATE queries a non-empty array is
     * required (an empty array will cause an exception when the query is built).
     *
     * @param array|string $fields Column names, or a single column name string.
     * @return $this
     * @throws InvalidArgumentException if $fields is not an array or string.
     */
    public function fields($fields)
    {
        if (is_string($fields)) {
            if (trim($fields) === '') {
                throw new InvalidArgumentException('Query::fields() expects a non-empty string column name or an array of column names.');
            }
            $fields = [$fields];
        } elseif (!is_array($fields)) {
            throw new InvalidArgumentException('Query::fields() expects an array of column names or a string column name.');
        }

        if (empty($fields)
            && isset($this->data['method'])
            && strtoupper($this->data['method']) === 'SELECT'
        ) {
            $fields = ['*'];
        } else {
            foreach ($fields as $field) {
                if (!is_string($field) || trim($field) === '') {
                    throw new InvalidArgumentException('Query::fields() expects every element to be a non-empty string column name.');
                }
            }
        }

        $this->data['fields'] = $fields;
        $this->query = null;
        return $this;
    }

    /**
     * Sets the WHERE clause.
     *
     * @param string $where Raw SQL WHERE fragment (use named placeholders, e.g. `id = :id`).
     * @return $this
     */
    public function where($where)
    {
        $this->data['where'] = $where;
        $this->query = null;
        return $this;
    }

    /**
     * Appends a single JOIN clause.
     *
     * @param string $join Full JOIN expression (e.g. `LEFT JOIN orders ON users.id = orders.user_id`).
     * @return $this
     * @throws InvalidArgumentException If $join is not a non-empty string.
     */
    public function join($join)
    {
        if (!is_string($join) || trim($join) === '') {
            throw new InvalidArgumentException(
                'join() expects a non-empty string JOIN expression.'
            );
        }
        if (!isset($this->data['joins'])) {
            $this->data['joins'] = [];
        } elseif (!is_array($this->data['joins'])) {
            $this->data['joins'] = [$this->data['joins']];
        }
        $this->data['joins'][] = $join;
        $this->query = null;
        return $this;
    }

    /**
     * Replaces all JOIN clauses with the given value.
     *
     * Accepts an array of JOIN expressions (each element must be a non-empty string),
     * a single JOIN string (normalized to a one-element array), or null (clears all joins).
     * Any other type throws an InvalidArgumentException.
     *
     * @param array|string|null $joins Array of JOIN expressions, a single JOIN string, or null.
     * @return $this
     * @throws InvalidArgumentException if $joins is not an array, string, or null, or if any
     *                                  array element is not a non-empty string.
     */
    public function joins($joins)
    {
        if ($joins === null) {
            $this->data['joins'] = [];
        } elseif (is_string($joins)) {
            if (trim($joins) === '') {
                throw new InvalidArgumentException(
                    'joins() expects a non-empty string JOIN expression.'
                );
            }
            $this->data['joins'] = [$joins];
        } elseif (is_array($joins)) {
            foreach ($joins as $join) {
                if (!is_string($join) || trim($join) === '') {
                    throw new InvalidArgumentException(
                        'joins() expects each element to be a non-empty string JOIN expression.'
                    );
                }
            }
            $this->data['joins'] = $joins;
        } else {
            throw new InvalidArgumentException(
                'joins() expects an array of JOIN expressions, a JOIN string, or null.'
            );
        }
        $this->query = null;
        return $this;
    }

    /**
     * Sets the GROUP BY clause.
     *
     * @param string $groupBy Column or expression to group by.
     * @return $this
     */
    public function groupBy($groupBy)
    {
        $this->data['group_by'] = $groupBy;
        $this->query = null;
        return $this;
    }

    /**
     * Sets the HAVING clause.
     *
     * @param string $having HAVING condition.
     * @return $this
     */
    public function having($having)
    {
        $this->data['having'] = $having;
        $this->query = null;
        return $this;
    }

    /**
     * Sets the ORDER BY clause.
     * The value is validated by validateOrderBy() when the query is built.
     *
     * @param string $orderBy Column(s) with optional ASC/DESC (e.g. `name ASC, id DESC`).
     * @return $this
     */
    public function orderBy($orderBy)
    {
        $this->data['order_by'] = $orderBy;
        $this->query = null;
        return $this;
    }

    /**
     * Sets the LIMIT clause.
     *
     * @param int $limit Maximum number of rows (must be a non-negative integer).
     * @return $this
     * @throws InvalidArgumentException If $limit is not a non-negative integer value.
     */
    public function limit($limit)
    {
        if (filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            throw new InvalidArgumentException(
                'limit() expects a non-negative integer.'
            );
        }
        $this->data['limit'] = (int) $limit;
        $this->query = null;
        return $this;
    }

    /**
     * Sets the OFFSET clause (SELECT only).
     *
     * @param int $offset Number of rows to skip (must be a non-negative integer).
     * @return $this
     * @throws InvalidArgumentException If $offset is not a non-negative integer value.
     */
    public function offset($offset)
    {
        if (filter_var($offset, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            throw new InvalidArgumentException(
                'offset() expects a non-negative integer.'
            );
        }
        $this->data['offset'] = (int) $offset;
        $this->query = null;
        return $this;
    }

    /**
     * Sets how many rows the INSERT query should prepare placeholders for.
     * Defaults to 1 when not called.
     *
     * @param int $count Number of rows to insert (must be a positive integer).
     * @return $this
     * @throws InvalidArgumentException If $count is not a positive integer value.
     */
    public function valuesCount($count)
    {
        if (filter_var($count, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new InvalidArgumentException(
                'valuesCount() expects a positive integer (>= 1).'
            );
        }
        $this->data['values_to_insert'] = (int) $count;
        $this->query = null;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Query builders
    // -------------------------------------------------------------------------

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
            $joins = is_array($this->data['joins']) ? $this->data['joins'] : [$this->data['joins']];
            foreach ($joins as $join) {
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
            $orderBy = self::validateOrderBy($this->data['order_by']);
            $sql .= " ORDER BY {$orderBy}";
        }

        // Limit
        $limit = filter_var(isset($this->data['limit']) ? $this->data['limit'] : null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($limit !== false && $limit > 0) {
            $sql .= " LIMIT " . $limit;
        }

        // Offset
        $offset = filter_var(isset($this->data['offset']) ? $this->data['offset'] : null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($offset !== false && $limit !== false && $limit > 0) {
            $sql .= " OFFSET " . $offset;
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

        $values = isset($this->data['values_to_insert']) ? (int) $this->data['values_to_insert'] : 1;

        if (!is_array($fields) || empty($fields)) {
            throw new InvalidArgumentException("Fields must be a non-empty array.");
        }

        if ($values < 1) {
            throw new InvalidArgumentException("Number of values to insert must be at least 1.");
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

        $sql = "UPDATE {$table}";

        if (!empty($this->data['joins'])) {
            $joins = is_array($this->data['joins']) ? $this->data['joins'] : [$this->data['joins']];
            foreach ($joins as $join) {
                $sql .= " {$join}";
            }
        }

        $sql .= " SET {$setClause}";

        // Prepare WHERE clause
        if (!empty($this->data['where'])) {
            $sql .= " WHERE {$this->data['where']}";
        }

        return $sql;
    }

    /**
     * Validates an ORDER BY value to prevent SQL injection.
     * Each token must be a valid SQL identifier (optionally table-qualified) followed
     * by an optional ASC or DESC keyword. Multiple columns may be separated by commas.
     * @param string $orderBy The ORDER BY value to validate.
     * @throws InvalidArgumentException if the value is not a string or contains disallowed characters.
     * @return string The trimmed, validated ORDER BY string.
     */
    public static function validateOrderBy($orderBy)
    {
        if (!is_string($orderBy)) {
            throw new InvalidArgumentException("order_by must be a string.");
        }

        $orderBy = trim($orderBy);

        // Each token: optional_table.column_name optional_ASC_DESC, separated by commas
        $pattern = '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?\s*(ASC|DESC)?'
            . '(\s*,\s*[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?\s*(ASC|DESC)?)*$/i';

        if (!preg_match($pattern, $orderBy)) {
            throw new InvalidArgumentException(
                "Invalid order_by value. Use column names with optional ASC/DESC, e.g. 'created_at DESC, id ASC'."
            );
        }

        return $orderBy;
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
     *    'where' => 'id = :id',
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
            $orderBy = self::validateOrderBy($this->data['order_by']);
            $sql .= " ORDER BY {$orderBy}";
        }

        $limit = filter_var(isset($this->data['limit']) ? $this->data['limit'] : null, FILTER_VALIDATE_INT);
        if ($limit !== false && $limit > 0) {
            $sql .= " LIMIT " . $limit;
        }

        return $sql;
    }
}
