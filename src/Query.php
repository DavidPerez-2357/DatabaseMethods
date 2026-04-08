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
    /**
     * Regex segment matching a single SQL identifier: letters, digits, and underscores,
     * must start with a letter or underscore. Used by all identifier-validation methods.
     */
    const IDENTIFIER = '[a-zA-Z_][a-zA-Z0-9_]*';

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
     */
    public function __construct(array $queryData = [])
    {
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
                    'Query::select() expects $fields to be a non-empty string, '
                    . 'an array (empty defaults to [\'*\']), or omitted.'
                );
            }
            $instance->data['fields'] = [$fields];
        } elseif (is_array($fields)) {
            // Delegate to fields() so per-element validation runs consistently.
            $instance->fields($fields);
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
            $instance->data['fields'] = self::normalizeOptionalFields($fields, 'Query::insert()');
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
            $instance->data['fields'] = self::normalizeOptionalFields($fields, 'Query::update()');
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
                throw new InvalidArgumentException(
                    'Query::fields() expects a non-empty string column name or an array of column names.'
                );
            }
            $fields = array($fields);
        } elseif (!is_array($fields)) {
            throw new InvalidArgumentException(
                'Query::fields() expects an array or string.'
            );
        }

        // Validate every element before applying any default.
        foreach ($fields as $field) {
            if (!is_string($field) || trim($field) === '') {
                throw new InvalidArgumentException(
                    'Query::fields() expects every element to be a non-empty string column name.'
                );
            }
        }

        // Empty array on SELECT defaults to 'SELECT *'.
        if (
            empty($fields)
            && isset($this->data['method'])
            && strtoupper($this->data['method']) === 'SELECT'
        ) {
            $fields = ['*'];
        }

        $this->data['fields'] = $fields;
        $this->query = null;
        return $this;
    }

    /**
     * Sets the WHERE clause.
     *
     * This is a raw SQL fragment. To prevent SQL injection, always pass user-supplied
     * values via PDO placeholders (e.g. `'id = :id'`) and bind them through Database.
     * Never interpolate untrusted strings directly into this value.
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
     * This is a raw SQL fragment. To prevent SQL injection, use only hard-coded or
     * pre-validated table/column names in JOIN expressions. Never interpolate untrusted
     * strings directly into the expression; any filter values belong in the WHERE clause
     * with PDO placeholders.
     *
     * @param string $join Full JOIN expression (e.g. `LEFT JOIN orders ON users.id = orders.user_id`).
     * @return $this
     * @throws InvalidArgumentException If $join is not a non-empty string.
     */
    public function join($join)
    {
        if (trim($join) === '') {
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
     * Appends an INNER JOIN clause.
     *
     * This is a convenience wrapper around join(). See join() for the SQL injection
     * warning that applies here as well.
     *
     * @param string $table     Table expression to join (e.g. `orders o`).
     * @param string $condition ON condition (e.g. `o.user_id = users.id`).
     * @return $this
     * @throws InvalidArgumentException If $table or $condition is not a non-empty string.
     */
    public function innerJoin($table, $condition)
    {
        return $this->buildTypedJoin('INNER JOIN', $table, $condition);
    }

    /**
     * Appends a LEFT JOIN clause.
     *
     * This is a convenience wrapper around join(). See join() for the SQL injection
     * warning that applies here as well.
     *
     * @param string $table     Table expression to join (e.g. `orders o`).
     * @param string $condition ON condition (e.g. `o.user_id = users.id`).
     * @return $this
     * @throws InvalidArgumentException If $table or $condition is not a non-empty string.
     */
    public function leftJoin($table, $condition)
    {
        return $this->buildTypedJoin('LEFT JOIN', $table, $condition);
    }

    /**
     * Appends a RIGHT JOIN clause.
     *
     * This is a convenience wrapper around join(). See join() for the SQL injection
     * warning that applies here as well.
     *
     * @param string $table     Table expression to join (e.g. `orders o`).
     * @param string $condition ON condition (e.g. `o.user_id = users.id`).
     * @return $this
     * @throws InvalidArgumentException If $table or $condition is not a non-empty string.
     */
    public function rightJoin($table, $condition)
    {
        return $this->buildTypedJoin('RIGHT JOIN', $table, $condition);
    }

    /**
     * Appends a FULL JOIN clause.
     *
     * This is a convenience wrapper around join(). See join() for the SQL injection
     * warning that applies here as well.
     *
     * @param string $table     Table expression to join (e.g. `orders o`).
     * @param string $condition ON condition (e.g. `o.user_id = users.id`).
     * @return $this
     * @throws InvalidArgumentException If $table or $condition is not a non-empty string.
     */
    public function fullJoin($table, $condition)
    {
        return $this->buildTypedJoin('FULL JOIN', $table, $condition);
    }

    /**
     * Replaces all JOIN clauses with the given value.
     *
     * Each JOIN expression is a raw SQL fragment — see join() for the SQL injection
     * warning that applies here as well.
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
            $this->data['joins'] = array();
        } elseif (is_string($joins)) {
            if (trim($joins) === '') {
                throw new InvalidArgumentException(
                    'joins() expects a non-empty string JOIN expression.'
                );
            }
            $this->data['joins'] = array($joins);
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
                'joins() expects an array of JOIN expressions, a single JOIN string, or null.'
            );
        }
        $this->query = null;
        return $this;
    }

    /**
     * Sets the GROUP BY clause.
     *
     * The value is validated by validateGroupBy() when the query is built.
     * Only plain column names (optionally table-qualified) are allowed, e.g.
     * `'users.id'` or `'id, name'`.
     *
     * @param string $groupBy Column(s) to group by, separated by commas.
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
     * This is a raw SQL fragment. To prevent SQL injection, always pass user-supplied
     * values via PDO placeholders and bind them through Database.
     * Never interpolate untrusted strings directly into this value.
     *
     * @param string $having HAVING condition (use named placeholders for user data).
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
        if (filter_var($limit, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0))) === false) {
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
        if (filter_var($offset, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0))) === false) {
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
        if (filter_var($count, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1))) === false) {
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
        $this->assertMethod('SELECT');
        $table = $this->requireTable();

        $fields = isset($this->data['fields']) ? implode(", ", $this->data['fields']) : "*";
        $sql = "SELECT {$fields} FROM {$table}";

        $this->appendJoinsToSql($sql);

        if (!empty($this->data['where'])) {
            $sql .= " WHERE {$this->data['where']}";
        }

        if (!empty($this->data['group_by'])) {
            $sql .= " GROUP BY " . self::validateGroupBy($this->data['group_by']);
        }

        if (!empty($this->data['having'])) {
            $sql .= " HAVING {$this->data['having']}";
        }

        if (!empty($this->data['order_by'])) {
            $sql .= " ORDER BY " . self::validateOrderBy($this->data['order_by']);
        }

        $limit = $this->getValidatedLimit();
        if ($limit > 0) {
            $sql .= " LIMIT " . $limit;
        }

        $offset = filter_var(
            isset($this->data['offset']) ? $this->data['offset'] : null,
            FILTER_VALIDATE_INT,
            array('options' => array('min_range' => 0))
        );
        if ($offset !== false) {
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
        $this->assertMethod('INSERT');
        $table = $this->requireTable();
        $fields = $this->requireFields();

        $values = isset($this->data['values_to_insert']) ? (int) $this->data['values_to_insert'] : 1;
        if ($values < 1) {
            throw new InvalidArgumentException("Number of values to insert must be at least 1.");
        }

        // Validate all column names once up-front. Field names must be plain
        // (unqualified) identifiers because they are also used as PDO named-placeholder
        // tokens like ":{$col}_{$i}". Dots are not valid in PDO placeholder names.
        foreach ($fields as $col) {
            self::validateUnqualifiedIdentifier($col, 'INSERT field');
        }

        $placeholders = array();
        for ($i = 0; $i < $values; $i++) {
            $rowPlaceholders = array();
            foreach ($fields as $col) {
                $rowPlaceholders[] = ":{$col}_{$i}";
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        return "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES " . implode(', ', $placeholders);
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
        $this->assertMethod('UPDATE');
        $table = $this->requireTable();
        $fields = $this->requireFields();

        $setClauses = array();
        foreach ($fields as $col) {
            // Field names must be plain (unqualified) identifiers because the PDO
            // placeholder is built as ":{$col}" — a dot in the name would make it invalid.
            self::validateUnqualifiedIdentifier($col, 'UPDATE field');
            $setClauses[] = "{$col} = :{$col}";
        }

        $sql = "UPDATE {$table}";
        $this->appendJoinsToSql($sql);
        $sql .= " SET " . implode(', ', $setClauses);

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
        $orderBy = trim($orderBy);
        $id = self::IDENTIFIER;

        // Each token: optional_table.column_name optional_ASC_DESC, separated by commas
        $pattern = '/^' . $id . '(\.' . $id . ')?\s*(ASC|DESC)?'
            . '(\s*,\s*' . $id . '(\.' . $id . ')?\s*(ASC|DESC)?)*$/i';

        if (!preg_match($pattern, $orderBy)) {
            throw new InvalidArgumentException(
                "Invalid order_by value. Use column names with optional ASC/DESC, e.g. 'created_at DESC, id ASC'."
            );
        }

        return $orderBy;
    }

    /**
     * Validates a GROUP BY value to prevent SQL injection.
     * Each token must be a valid SQL identifier (optionally table-qualified).
     * Multiple columns may be separated by commas.
     * @param string $groupBy The GROUP BY value to validate.
     * @throws InvalidArgumentException if the value is not a string or contains disallowed characters.
     * @return string The trimmed, validated GROUP BY string.
     */
    public static function validateGroupBy($groupBy)
    {
        $groupBy = trim($groupBy);
        $id = self::IDENTIFIER;

        // Each token: optional_table.column_name, separated by commas (no ASC/DESC)
        $pattern = '/^' . $id . '(\.' . $id . ')?(\s*,\s*' . $id . '(\.' . $id . ')?)*$/';

        if (!preg_match($pattern, $groupBy)) {
            throw new InvalidArgumentException(
                "Invalid group_by value. Use plain column names, e.g. 'users.id' or 'id, name'."
            );
        }

        return $groupBy;
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
        $this->assertMethod('DELETE');
        $table = $this->requireTable();

        $sql = "DELETE FROM {$table}";

        if (!empty($this->data['where'])) {
            $sql .= " WHERE {$this->data['where']}";
        }

        if (!empty($this->data['order_by'])) {
            $sql .= " ORDER BY " . self::validateOrderBy($this->data['order_by']);
        }

        $limit = $this->getValidatedLimit();
        if ($limit > 0) {
            $sql .= " LIMIT " . $limit;
        }

        return $sql;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Asserts that $this->data['method'] matches the expected value.
     * @param string $expected Expected method name (e.g. 'SELECT').
     * @throws InvalidArgumentException
     */
    private function assertMethod($expected)
    {
        if (!isset($this->data['method']) || strtoupper($this->data['method']) !== $expected) {
            throw new InvalidArgumentException("Only {$expected} method is supported.");
        }
    }

    private function requireTable()
    {
        if (!isset($this->data['table'])) {
            throw new InvalidArgumentException("Table is required.");
        }
        self::validateIdentifier($this->data['table'], 'table name');
        return $this->data['table'];
    }

    private function requireFields()
    {
        if (!isset($this->data['fields']) || !is_array($this->data['fields']) || empty($this->data['fields'])) {
            throw new InvalidArgumentException("Fields must be a non-empty array.");
        }
        return $this->data['fields'];
    }

    private function appendJoinsToSql(&$sql)
    {
        if (empty($this->data['joins'])) {
            return;
        }
        $joins = is_array($this->data['joins']) ? $this->data['joins'] : array($this->data['joins']);
        foreach ($joins as $join) {
            $sql .= " {$join}";
        }
    }

    private function buildTypedJoin($type, $table, $condition)
    {
        if (trim($table) === '') {
            throw new InvalidArgumentException(
                'typed join expects $table to be a non-empty string.'
            );
        }
        if (trim($condition) === '') {
            throw new InvalidArgumentException(
                'typed join expects $condition to be a non-empty string.'
            );
        }
        return $this->join("{$type} {$table} ON {$condition}");
    }

    private function getValidatedLimit()
    {
        $raw = isset($this->data['limit']) ? $this->data['limit'] : null;
        $limit = filter_var($raw, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));
        return ($limit !== false && $limit > 0) ? (int) $limit : 0;
    }

    private static function normalizeOptionalFields($fields, $context)
    {
        if (is_string($fields)) {
            return array($fields);
        }
        return $fields;
    }

    /**
     * Validates that a value is a safe SQL identifier (or schema-qualified identifier).
     * Allows `identifier` or `schema.identifier` — the same character set used by
     * validateOrderBy() and validateGroupBy().
     *
     * This is the primary guard against SQL injection for table names and column names
     * that are interpolated directly into the query string.
     *
     * @param string $name    The identifier to validate.
     * @param string $context Human-readable name used in exception messages (e.g. 'table name').
     * @throws InvalidArgumentException if $name is not a safe SQL identifier.
     */
    public static function validateIdentifier($name, $context)
    {
        $id = self::IDENTIFIER;
        if (!preg_match('/^' . $id . '(\.' . $id . ')?$/', $name)) {
            throw new InvalidArgumentException(
                "Invalid {$context}: only alphanumeric characters and underscores are allowed"
                . " (optionally schema-qualified, e.g. 'schema.table')."
            );
        }
    }

    /**
     * Validates that a value is a plain (unqualified) SQL identifier with no dots.
     *
     * Use this instead of validateIdentifier() for INSERT/UPDATE column names, because
     * those names are also embedded in PDO named-placeholder tokens (e.g. `:col_0`).
     * PDO does not accept dots in placeholder names, so `schema.column` must be rejected.
     *
     * @param string $name    The identifier to validate.
     * @param string $context Human-readable name used in exception messages (e.g. 'INSERT field').
     * @throws InvalidArgumentException if $name is not a plain SQL identifier.
     */
    public static function validateUnqualifiedIdentifier($name, $context)
    {
        if (!preg_match('/^' . self::IDENTIFIER . '$/', $name)) {
            throw new InvalidArgumentException(
                "Invalid {$context}: must start with a letter or underscore and contain only"
                . " alphanumeric characters and underscores (unqualified column name, e.g. 'email' or 'created_at')."
            );
        }
    }
}
