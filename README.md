# DatabaseMethods

A lightweight PHP library that simplifies database work with two focused tools:

- **`Query`** — builds clean, readable SQL queries from a plain array.
- **`Database`** — a PDO-based class with intuitive methods for selecting, inserting, updating, deleting, counting, and running transactions.

By using these tools you'll cut repetitive boilerplate, letting you focus on building features instead.

> [!NOTE]
> This library is compatible with PHP **5.4** and above.

## Installation / Usage

Just require the single entry-point file — it automatically loads every module:

```php
require_once 'DatabaseMethods.php';
```

## Repository structure

```
DatabaseMethods.php          ← entry point (require this file)
src/
  Query.php                  ← SQL query builder
  Database.php               ← base Database class (CRUD, transactions, …)
  drivers/
    Mysql.php                ← MySQL / MariaDB driver
    Postgres.php             ← PostgreSQL driver
    Sqlite.php               ← SQLite driver
    Sql.php                  ← Microsoft SQL Server driver
```

## Query class
The `Query` class builds SQL query strings from PHP. It supports two equivalent styles:

* **Array constructor** — the original API, fully supported.
* **Fluent API** — static factory methods combined with chainable setters for a more readable, IDE-friendly experience.

Both styles produce identical SQL and can be used interchangeably. You can cast a `Query` object to a string with `echo` or string concatenation, or call the explicit `getQuery()` method.

> [!TIP]
> Prefer `getQuery()` over string casting when you need reliable error handling. Casting to string catches build errors internally and returns an empty string with an `E_USER_WARNING`, whereas `getQuery()` propagates the exception to the caller.

### Fluent API — quick reference

| Factory method | Description |
|---|---|
| `Query::select($fields)` | Start a SELECT query |
| `Query::insert($table[, $fields])` | Start an INSERT query (`$fields` is optional; you can also call `->fields(...)` later) |
| `Query::update($table[, $fields])` | Start an UPDATE query (`$fields` is optional; you can also call `->fields(...)` later) |
| `Query::delete($table)` | Start a DELETE query |

| Chainable setter | Applies to | Description |
|---|---|---|
| `->from($table)` / `->table($table)` | SELECT | Set the target table (primary use; also works as a setter on any query type) |
| `->fields($fields)` | SELECT, INSERT, UPDATE | Set the column list |
| `->where($expr)` | SELECT, UPDATE, DELETE | Set the WHERE clause |
| `->join($join)` | SELECT, UPDATE | Append one JOIN clause |
| `->joins($joins)` | SELECT, UPDATE | Replace all JOINs at once |
| `->groupBy($expr)` | SELECT | Set GROUP BY |
| `->having($expr)` | SELECT | Set HAVING |
| `->orderBy($expr)` | SELECT, DELETE | Set ORDER BY |
| `->limit($n)` | SELECT, DELETE | Set LIMIT |
| `->offset($n)` | SELECT | Set OFFSET |
| `->valuesCount($n)` | INSERT | Number of rows to insert (default 1) |

---

### Select query

**Fluent API:**
```php
$query = Query::select(['id', 'name'])
    ->from('users')
    ->join('LEFT JOIN orders ON users.id = orders.user_id')
    ->where('users.active = 1')
    ->groupBy('users.id')
    ->having('COUNT(orders.id) > 0')
    ->orderBy('users.name ASC')
    ->limit(10);
```

**Array constructor (equivalent):**
```php
$query = new Query([
    'method' => 'SELECT',
    'fields' => ['id', 'name'],
    'table' => 'users',
    'joins' => ['LEFT JOIN orders ON users.id = orders.user_id'],
    'where' => 'users.active = 1',
    'group_by' => 'users.id',
    'having' => 'COUNT(orders.id) > 0',
    'order_by' => 'users.name ASC',
    'limit' => 10
]);
```

The resulting query will be:
```SQL
SELECT id, name FROM users 
LEFT JOIN orders ON users.id = orders.user_id 
WHERE users.active = 1 
GROUP BY users.id HAVING COUNT(orders.id) > 0 
ORDER BY users.name ASC 
LIMIT 10
``` 

### PDO Insert query

**Fluent API:**
```php
$query = Query::insert('users', ['name', 'email'])->valuesCount(3);
```

**Array constructor (equivalent):**
```php
$query = new Query([
    'method' => 'INSERT',
    'table' => 'users',
    'fields' => ['name', 'email'],
    'values_to_insert' => 3
]);
```
The `valuesCount()` / `values_to_insert` field determines how many rows are prepared in the query. It defaults to 1 when omitted.

The resulting query will be:
```SQL
INSERT INTO users (name, email) 
VALUES (:name_0, :email_0), (:name_1, :email_1), (:name_2, :email_2)
```

### PDO Update query

**Fluent API:**
```php
$query = Query::update('users', ['name', 'email'])
    ->join('LEFT JOIN orders ON users.id = orders.user_id')
    ->where('id = :id');
```

**Array constructor (equivalent):**
```php
$query = new Query([
    'method' => 'UPDATE',
    'table' => 'users',
    'fields' => ['name', 'email'],
    'where' => 'id = :id',
    'joins' => ['LEFT JOIN orders ON users.id = orders.user_id']
]);
```

The resulting query will be:
```SQL
UPDATE users
LEFT JOIN orders ON users.id = orders.user_id
SET name = :name, email = :email
WHERE id = :id
```

### Delete query

**Fluent API:**
```php
$query = Query::delete('users')
    ->where('id = :id')
    ->orderBy('created_at DESC')
    ->limit(10);
```

**Array constructor (equivalent):**
```php
$query = new Query([
    'method' => 'DELETE',
    'table' => 'users',
    'where' => 'id = :id',
    'order_by' => 'created_at DESC',
    'limit' => 10
]);
```

The resulting query will be:
```SQL
DELETE FROM users
WHERE id = :id
ORDER BY created_at DESC
LIMIT 10
```

> [!WARNING]
> The `where` value is embedded as a raw SQL fragment. Always use named placeholders (e.g. `id = :id`) and pass the actual values via the binding array when executing the query through the `Database` class. The `order_by` / `orderBy()` value is validated against a strict pattern that allows only identifiers made of letters, digits, and underscores (optionally qualified with dots), separated by commas and arbitrary whitespace, with optional `ASC`/`DESC` keywords — any other characters will throw an `InvalidArgumentException`.

### Identifier validation rules

Some inputs are validated strictly and will throw `InvalidArgumentException` for values that would previously have been passed through unchecked.

**Table names** (`->from()`, `->table()`, `Query::delete($table)`, etc.):
- Must be a plain identifier: letters, digits, and underscores, starting with a letter or underscore (e.g. `users`, `order_items`).
- Optionally schema-qualified with a single dot: `schema.table` (e.g. `myschema.orders`).
- Must **not** include quoting, whitespace, aliases, or arbitrary SQL fragments (e.g. `` `users` ``, `"users"`, `users u`, `users AS u` are all rejected).

**INSERT / UPDATE column names** (`->fields()`, `Query::insert($table, $fields)`, etc.):
- Must be plain, **unqualified** identifiers (no dots) — e.g. `email`, `created_at`.
- Qualified names like `users.email` are **rejected** because the column name is also used to construct a PDO named-placeholder token (e.g. `:email_0`), and PDO does not allow dots in placeholder names.

**GROUP BY / ORDER BY**:
- Must be one or more plain identifiers (optionally table-qualified), separated by commas.
- `ORDER BY` additionally allows optional `ASC` / `DESC` per column.
- Raw SQL expressions, function calls, or subqueries are not accepted.

**WHERE, HAVING, JOIN** (raw SQL fragments):
- These are passed through as-is.
- Only hard-coded or otherwise fully-trusted strings should appear directly in these expressions.

> [!WARNING]
> **Never interpolate user-controlled values directly into WHERE, HAVING, or JOIN strings** — doing so creates an SQL injection vulnerability. Always use PDO named placeholders for any user-supplied values (e.g. `age > :min_age`) and bind the actual values through `Database`.

## Database class
The Database class provides a comprehensive set of methods for essential database operations such as select, insert, update, delete, record counting, and transaction management, making it straightforward to handle both simple and complex database tasks.

### Creating a database object

The **Mysql**, **Postgres**, **Sql**, and **Sqlite** driver classes all extend the parent class **Database**, which provides the shared CRUD and transaction methods. To create a driver object, pass a configuration array with the following properties:

| Canonical key   | Accepted alias | Description                                                                 | Required by                                    |
|-----------------|----------------|-----------------------------------------------------------------------------|-----------------------------------------------|
| `serverName`    | `host`         | Hostname or IP address of the database server                               | Mysql, Postgres, Sql                          |
| `username`      | `user`         | Database user name                                                          | Mysql, Postgres, Sql                          |
| `password`      | *(none)*       | Password for the database user (default: `""`)                              | Mysql, Postgres, Sql                          |
| `DB`            | `dbname`       | Database/schema name (Mysql, Postgres, Sql) or database file path (Sqlite) | Mysql (optional), Postgres (optional), Sql, Sqlite |
| `codification`  | *(none)*       | Character encoding (default: `utf8mb4` / `utf8`)                            | Mysql, Postgres                               |

```php
$properties = [
    "serverName" => "localhost",
    "username" => "root",
    "password" => "",
    "DB" => "your_database",
    "codification" => "utf8"
];

$mysql_object    = new Mysql($properties);
$sql_object      = new Sql($properties);
$postgres_object = new Postgres($properties);

// SQLite only requires the "DB" key (path to the database file)
$sqlite_object = new Sqlite(["DB" => "/path/to/database.sqlite"]);
```

> [!NOTE]
> The `Sqlite` driver only uses the `DB` key (the path to the `.sqlite` file). The `serverName`, `username`, `password`, and `codification` keys are not required and will be ignored if present.
>
> The `Sql` (SQL Server) driver requires `serverName`, `username`, and `DB` — it throws an `InvalidArgumentException` if any of these are missing. `password` is optional and defaults to `""`.

---

### JSON encode option

You can enable JSON encoding for the results of select methods by calling the following method on your database object:

```php
$mysql_object->setJsonEncode(true);
```

When this option is enabled, the results of select methods (such as `select`, `selectOne`, and `plainSelect`) will be returned as JSON strings instead of arrays.  
This can be useful if you want to directly output or transmit the results in JSON format.

To disable JSON encoding and return results as arrays, simply call:

```php
$mysql_object->setJsonEncode(false);
```

---

### Keywords in query variables
When using query variables, you can include special keywords that will be automatically replaced with their corresponding values before the query is executed. 

Available keywords:

| Keyword              | Replaced with                        |
|----------------------|--------------------------------------|
| `@lastInsertId`      | Last auto-increment ID inserted      |
| `@currentDate`       | Current date (`Y-m-d`)               |
| `@currentDateTime`   | Current date and time (`Y-m-d H:i:s`)|
| `@currentTime`       | Current time (`H:i:s`)               |
| `@currentTimestamp`  | Unix timestamp                       |
| `@currentYear`       | Current year (`Y`)                   |
| `@currentMonth`      | Current month (`m`)                  |
| `@currentDay`        | Current day (`d`)                    |
| `@currentWeekday`    | Day name (e.g. `Monday`)             |
| `@randomString`      | Random 8-character alphanumeric string |
| `@randomInt`         | Random integer between 1 and 9999    |
| `@randomFloat`       | Random float between 0.01 and 99.99  |
| `@randomBoolean`     | Random `true` or `false`             |

You can add more keywords by editing the `replaceKeywordsInData` method in `Database.php`.

Here is an example of use:
```php
$data = [
    'name'=> '@randomString',
    'created_at'=> '@currentDateTime',
];

try {
    $database->update('users', $data, 'id = :id', ['id' => '@lastInsertId']);
} catch (Exception $e) {
    echo 'Error: '. $e->getMessage();
}
```

Keywords also work in multi-record inserts:
```php
$data = [
    ['name' => '@randomString', 'created_at' => '@currentDateTime'],
    ['name' => '@randomString', 'created_at' => '@currentDateTime'],
];

try {
    $database->insert('users', $data);
} catch (Exception $e) {
    echo 'Error: '. $e->getMessage();
}
```
> [!NOTE]
> Keywords are supported in all CRUD methods (including multi-row inserts via `insert()`), but **not** in the plain query helpers `executePlainQuery()` and `plainSelect()`.

---

### Executing a plain query
If you don't want to use the specific methods described below, you can execute any SQL statement directly with this method. It accepts the following parameters:

* **query**: A SQL string or a `Query` object.

* **data**: An optional associative array of query bindings.

This method returns `true` on success, or throws an exception if an error occurs.

```php
try {
    $database->executePlainQuery(
        "UPDATE users SET active = 0 WHERE id = :userId",
        ["userId" => 2]
    );
    echo "Record updated successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

> [!NOTE]
> Query parameters must use named PDO placeholders prefixed with `:` (e.g. `:userId`).

---

### Execute plain SELECT query
This method works similarly to `executePlainQuery`, but is specifically designed for SELECT statements:

* **query**: A SQL string or a `Query` object.

* **data**: An optional associative array of query bindings.

It returns all matching rows as an associative array, or throws an exception if an error occurs.

```php
try {
    $result = $database->plainSelect(
        "SELECT id, name FROM users WHERE id = :userId",
        ["userId" => 2]
    );
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

---

### Select statement

The `select` and `selectOne` methods retrieve records from the database using a `Query` object. `select` returns all matching rows; `selectOne` returns only the first row.

**Example using `select`:**
```php
$query = Query::select(['id', 'name'])
    ->from('users')
    ->where('id = :userId');

try {
    $result = $database->select($query, ["userId" => 2]);
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

**Example using `selectOne`:**
```php
$query = Query::select(['id', 'name'])
    ->from('users')
    ->where('id = :userId');

try {
    $result = $database->selectOne($query, ["userId" => 2]);
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```
---

### Insert statement
Inserts one or more records into the specified table. The method automatically detects whether you are inserting a single record (associative array) or multiple records (array of associative arrays), and returns the last inserted auto-increment ID in both cases.

**Example inserting a single record:**
```php
$data = [
    'name' => 'John',
    'email' => 'john@email.com'
];

try {
    $lastId = $database->insert('users', $data);
    echo "Inserted ID: " . $lastId;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

**Example inserting multiple records:**
```php
$data = [
    ['name' => 'Alice', 'email' => 'alice@email.com'],
    ['name' => 'Bob', 'email' => 'bob@email.com']
];

try {
    $lastId = $database->insert('users', $data);
    echo "Last inserted ID: " . $lastId;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

---

### Update statement
Updates records in the specified table. Returns the number of affected rows.

**Signature:** `update($table, $data, $where, $whereData = [], $joins = [])`

| Parameter   | Description |
|-------------|-------------|
| `$table`    | Table name |
| `$data`     | Associative array of columns and new values |
| `$where`    | WHERE clause (use named placeholders) |
| `$whereData`| Optional named bindings for `$where` |
| `$joins`    | Optional array of JOIN clauses |

> [!CAUTION]
> Keys in `$whereData` must not overlap with column names in `$data`. Positional placeholders (`?`) are not supported in `$where` for `update()` — use named placeholders (e.g. `id = :id`) instead.

```php
$data = [
    'name' => 'Michael',
    'email' => 'michael@email.com'
];

try {
    $affected = $database->update('users', $data, 'id = :id', ['id' => 5]);
    echo "Rows updated: " . $affected;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

---

### Delete statement
Deletes records from the specified table. Returns the number of affected rows.

**Signature:** `delete($table, $where, $whereData = [], $orderBy = "", $limit = 0)`

Both named (`id = :id`) and positional (`id = ?`) placeholders are supported in `$where`.

```php
try {
    $deleted = $database->delete('users', 'id = :id', ['id' => 2]);
    echo "Rows deleted: " . $deleted;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

The `$orderBy` parameter must be a comma-separated list of column identifiers (letters, digits, underscores, and dots) with each column optionally followed by `ASC` or `DESC` (e.g. `'created_at DESC'`). Passing any other characters will throw an `InvalidArgumentException`.

To delete all records from a table (optionally with `$orderBy` and `$limit`), use `deleteAll`:

**Signature:** `deleteAll($table, $data = [], $orderBy = "", $limit = 0)`

> [!NOTE]
> The `$data` parameter is a bindings array that is passed directly to PDO. Since `deleteAll` builds a DELETE with no WHERE clause, it has no bound parameters and `$data` should always be left as `[]`. Pass `$orderBy` and `$limit` as the 3rd and 4th arguments when needed.

```php
try {
    $deleted = $database->deleteAll('users');
    echo "Rows deleted: " . $deleted;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

---

### Count statement
Returns the number of records that match a condition.

**Signature:** `count($table, $where = '', $whereData = [], $joins = [])`

Both named (`active = :active`) and positional (`active = ?`) placeholders are supported in `$where`.

```php
try {
    $total = $database->count('users', 'active = :active', ['active' => 1]);
    echo "Active users: " . $total;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

---

### Transactions
Execute multiple operations inside a single transaction using `executeTransaction`. The transaction is automatically committed on success or rolled back if any operation throws an exception.

```php
try {
    $database->executeTransaction(function($db) {
        $db->update('users', ['active' => 0], 'id = :id', ['id' => 2]);
        $db->delete('orders', 'user_id = :user_id', ['user_id' => 2]);
    });
    echo "Transaction completed.";
} catch (Exception $e) {
    echo "Transaction error: " . $e->getMessage();
}
```

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
