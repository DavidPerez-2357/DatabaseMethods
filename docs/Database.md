# Database

The `Database` class provides a PDO-based interface for selecting, inserting, updating, deleting, counting, and running transactions. Driver subclasses (`Mysql`, `Postgres`, `Sql`, `Sqlite`) extend it and handle the connection details.

&emsp;

## Creating a connection

Pass a configuration array to the driver constructor:

| Key | Alias | Description | Required by |
|---|---|---|---|
| `serverName` | `host` | Hostname or IP | Mysql, Postgres, Sql |
| `username` | `user` | Database user | Mysql, Postgres, Sql |
| `password` | *(none)* | Password (default `""`) | Mysql, Postgres, Sql |
| `DB` | `dbname` | Database name or file path | All |
| `codification` | *(none)* | Encoding (default `utf8mb4`/`utf8`) | Mysql, Postgres |

```php
$props = [
    'serverName'   => 'localhost',
    'username'     => 'root',
    'password'     => '',
    'DB'           => 'your_database',
    'codification' => 'utf8'
];

$mysql    = new Mysql($props);
$postgres = new Postgres($props);
$sql      = new Sql($props);
$sqlite   = new Sqlite(['DB' => '/path/to/database.sqlite']);
```

> [!NOTE]
> `Sqlite` only uses `DB` (path to the `.sqlite` file); all other keys are ignored.
>
> `Sql` (SQL Server) requires `serverName`, `username`, and `DB`; `password` defaults to `""`.

&emsp;

## JSON encode

To receive results as a JSON string instead of a PHP array:

```php
$mysql->setJsonEncode(true);   // enable
$mysql->setJsonEncode(false);  // disable (default)

// setJsonEncode() returns $this, so it can be chained:
$result = $mysql->setJsonEncode(true)->select(Query::select()->from('users'));
```

Affects `select`, `selectOne`, and `plainSelect`.

&emsp;

## Special keywords

Certain string values in the `$data` / `$whereData` arrays are replaced automatically before execution:

| Keyword | Value |
|---|---|
| `@lastInsertId` | Last auto-increment ID |
| `@currentDate` | `Y-m-d` |
| `@currentDateTime` | `Y-m-d H:i:s` |
| `@currentTime` | `H:i:s` |
| `@currentTimestamp` | Unix timestamp |
| `@currentYear` | `Y` |
| `@currentMonth` | `m` |
| `@currentDay` | `d` |
| `@currentWeekday` | e.g. `Monday` |
| `@randomString` | Random 8-char alphanumeric |
| `@randomInt` | Random integer 1–9999 |
| `@randomFloat` | Random float 0.01–99.99 |
| `@randomBoolean` | `true` or `false` |

Keywords work in all CRUD methods (including multi-row inserts) but **not** in `runPlainQuery()` or `plainSelect()`.

To add custom keywords, edit the `replaceKeywordsInData` method in `Database.php`.

```php
$database->update('users',
    ['name' => '@randomString', 'created_at' => '@currentDateTime'],
    'id = :id',
    ['id' => '@lastInsertId']
);
```

### Disabling keyword checking

Keyword replacement is enabled by default. Call `enableKeywordCkeck(false)` to pass data values through unmodified.

Disabling keyword checking reduces processing complexity on every query call, which improves speed when you know your data contains no `@`-prefixed keywords or when maximum throughput is required (e.g. high-volume batch inserts).

> [!TIP]
> If your application never uses special keywords like `@currentDate` or `@randomInt`, disable keyword checking right after creating the database instance. This avoids unnecessary processing on every query and gives you better performance for free.

```php
$database->enableKeywordCkeck(false);
// '@currentDate' is now stored as the literal string, not today's date
$database->insert('logs', ['event' => '@currentDate']);
$database->enableKeywordCkeck(true); // re-enable when done
```

`enableKeywordCkeck()` returns `$this` so it can be chained.

&emsp;

## Methods

### `runPlainQuery($query, $data = [])`

Execute any SQL write statement directly. Always returns the PDO-reported `rowCount()` as an integer (may be 0 for DDL statements). Use `plainSelect()` for queries that return a result set.

Throws on error.

```php
// Write query — returns affected row count
$affected = $database->runPlainQuery(
    'UPDATE users SET active = 0 WHERE id = :userId',
    ['userId' => 2]
);
// $affected === 1
```

---

### `plainSelect($query, $data = [])`

Execute a plain SQL query and return all result rows as an associative array, or a JSON-encoded string when json_encode mode is enabled. Use `runPlainQuery()` for write statements.

Throws on error.

```php
$rows = $database->plainSelect('SELECT * FROM users WHERE active = 1');
// $rows === [['id' => 1, 'name' => 'Alice', ...], ...]

$rows = $database->plainSelect(
    'SELECT * FROM users WHERE id = :id',
    ['id' => 1]
);
```

---

### `select($query, $data = [])` / `selectOne($query, $data = [])`

Execute a `Query` object or a raw SQL string. `select` returns all matching rows; `selectOne` returns only the first row.

```php
// Using a Query object
$query = Query::select(['id', 'name'])->from('users')->where('id = :userId');

$rows = $database->select($query, ['userId' => 2]);
$row  = $database->selectOne($query, ['userId' => 2]);

// Using a raw SQL string
$rows = $database->select('SELECT id, name FROM users WHERE id = :userId', ['userId' => 2]);
$row  = $database->selectOne('SELECT id, name FROM users WHERE id = :userId LIMIT 1', ['userId' => 2]);
```

---

### `insert($table, $data)`

Insert one or more records. Auto-detects single (associative array) vs. multiple (array of associative arrays). Returns the last inserted auto-increment ID.

```php
// Single record
$lastId = $database->insert('users', ['name' => 'John', 'email' => 'john@email.com']);

// Multiple records
$lastId = $database->insert('users', [
    ['name' => 'Alice', 'email' => 'alice@email.com'],
    ['name' => 'Bob',   'email' => 'bob@email.com'],
]);
```

---

### `update($table, $data, $where, $whereData = [], $joins = [])`

Update records. Returns the number of affected rows.

> [!CAUTION]
> Keys in `$whereData` must not overlap with column names in `$data`. Positional placeholders (`?`) are not supported in `$where` - use named placeholders (e.g. `id = :id`).

```php
$affected = $database->update('users',
    ['name' => 'Michael', 'email' => 'michael@email.com'],
    'id = :id',
    ['id' => 5]
);
```

---

### `delete($table, $where, $whereData = [], $orderBy = "", $limit = 0)`

Delete records matching `$where`. Returns the number of affected rows. Both named and positional placeholders are supported.

```php
$deleted = $database->delete('users', 'id = :id', ['id' => 2]);
```

`$orderBy` must be a comma-separated list of plain column identifiers with optional `ASC`/`DESC` (e.g. `'created_at DESC'`); any other characters throw `InvalidArgumentException`.

---

### `deleteAll($table, $orderBy = "", $limit = 0)`

Delete all records from a table (no WHERE clause). Pass `$orderBy` and `$limit` as the 2nd and 3rd arguments when needed.

```php
$deleted = $database->deleteAll('users');
```

---

### `count($table, $where = '', $whereData = [], $joins = [])`

Return the number of records matching a condition. Both named and positional placeholders are supported.

```php
$total = $database->count('users', 'active = :active', ['active' => 1]);
```

---

### `selectWhere($table, $columns, $conditions)`

Fetch specific columns with simple equality filters. Returns all matching rows.

```php
$rows = $database->selectWhere('users', ['id', 'name'], ['active' => 1]);
// Generated SQL: SELECT id, name FROM users WHERE active = :active

// Empty $columns returns all columns ('*')
$rows = $database->selectWhere('users', [], ['active' => 1]);

// Empty $conditions returns all rows (no WHERE clause)
$rows = $database->selectWhere('users', ['id', 'name'], []);
```

---

### `selectOneWhere($table, $columns, $conditions)`

Same as `selectWhere()` but returns a single row (appends `LIMIT 1`). Returns an empty array when no row is found.

```php
$row = $database->selectOneWhere('users', ['id', 'email'], ['id' => $userId]);
// Generated SQL: SELECT id, email FROM users WHERE id = :id LIMIT 1
```

---

### `existsWhere($table, $conditions)`

Returns `true` if at least one row in `$table` matches all `$conditions`, `false` otherwise. Uses `SELECT 1 … LIMIT 1` for minimal overhead.

> [!CAUTION]
> `$conditions` must not be empty — an empty array throws `InvalidArgumentException`.

```php
$exists = $database->existsWhere('users', ['email' => $email]);
// Generated SQL: SELECT 1 FROM users WHERE email = :email LIMIT 1
```

---

### `countWhere($table, $conditions)`

Returns the number of rows in `$table` that match all `$conditions`. An empty `$conditions` array counts all rows.

```php
$total = $database->countWhere('orders', ['status' => 'pending']);
// Generated SQL: SELECT COUNT(*) as total FROM orders WHERE status = :status
```

---

### `updateWhere($table, $data, $conditions)`

Update rows using explicit equality conditions. Returns the number of affected rows.

> [!CAUTION]
> Both `$data` and `$conditions` must not be empty — an empty array for either throws `InvalidArgumentException` to prevent accidental full-table updates.

```php
$affected = $database->updateWhere(
    'users',
    ['last_login' => $timestamp],
    ['id' => $userId]
);
// Generated SQL: UPDATE users SET last_login = :last_login WHERE id = :w_id
```

---

### `deleteWhere($table, $conditions)`

Delete rows with safe condition handling. Returns the number of affected rows.

> [!CAUTION]
> `$conditions` must not be empty — an empty array throws `InvalidArgumentException` to prevent accidental full-table deletes.

```php
$deleted = $database->deleteWhere('sessions', ['user_id' => $userId]);
// Generated SQL: DELETE FROM sessions WHERE user_id = :user_id
```

---

### `selectWhereIn($table, $columns, $column, $values)`

Fetch rows where a column's value appears in a list. Useful for batch retrieval.

> [!NOTE]
> `$values` must not be empty — an empty array throws `InvalidArgumentException`.

```php
$rows = $database->selectWhereIn('users', ['id', 'name'], 'id', $userIds);
// Generated SQL: SELECT id, name FROM users WHERE id IN (:id_0, :id_1, :id_2)
```

---

### `selectOrderedWhere($table, $columns, $conditions, $orderBy)`

Fetch rows matching `$conditions` sorted by the columns in `$orderBy`. The direction for each column must be `'ASC'` or `'DESC'` (case-insensitive). An empty `$orderBy` array omits the `ORDER BY` clause.

```php
$rows = $database->selectOrderedWhere(
    'posts',
    ['id', 'title'],
    ['published' => 1],
    ['created_at' => 'DESC']
);
// Generated SQL: SELECT id, title FROM posts WHERE published = :published ORDER BY created_at DESC
```

---

### `paginateWhere($table, $columns, $conditions, $limit, $offset = 0)`

Fetch a paginated subset of rows matching `$conditions`. `$limit` must be a positive integer; `$offset` must be a non-negative integer (defaults to `0`).

```php
$rows = $database->paginateWhere(
    'products',
    ['id', 'name'],
    ['active' => 1],
    20,
    0
);
// Generated SQL: SELECT id, name FROM products WHERE active = :active LIMIT 20 OFFSET 0
```

---

### `executeTransaction(callable $callback)`

Run multiple operations inside a single transaction. Commits automatically on success; rolls back if any operation throws.

```php
$database->executeTransaction(function($db) {
    $db->update('users', ['active' => 0], 'id = :id', ['id' => 2]);
    $db->delete('orders', 'user_id = :user_id', ['user_id' => 2]);
});
```
