# DatabaseMethods

A lightweight PHP library that simplifies database work with two focused tools:

- **`Query`** — builds clean, readable SQL queries from a plain array.
- **`Database`** — a PDO-based class with intuitive methods for selecting, inserting, updating, deleting, counting, and running transactions.

By using these tools you'll cut repetitive boilerplate, letting you focus on building features instead.

> **Compatibility:**  
> This library is compatible with PHP **version 5.4** and above.

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
The Query class is used to build a query from an object. You can use the class as a string directly.

```PHP
$query = new Query([
    'method' => 'SELECT',
    'fields' => ['id', 'name'],
    'table' => 'users',
]);

echo $query;
```

Here are some examples:

### Select query
This is an example of a SELECT query using all available fields:
```PHP
$query = new Query([
    'method' => 'SELECT',
    'fields' => ['id', 'name'],
    'table' => 'users',
    'joins' => ['LEFT JOIN orders ON users.id = orders.user_id'],
    'where' => 'users.active = 1',
    'group_by' => 'users.id',
    'having' => 'COUNT(orders.id) > 0',
    'order_by' => 'users.name ASC',
    'limit' => 10,
    'offset' => 0
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
This is an example of a PDO INSERT query using all available fields:

```PHP
$query = new Query([
    'method' => 'INSERT',
    'table' => 'users',
    'fields' => ['name', 'email'],
    'values_to_insert' => 3
]);
```
The **values_to_insert** field determine how many registers are going to be inserted in this query.

The resulting query will be:
```SQL
INSERT INTO users (name, email) 
VALUES (:name_0, :email_0), (:name_1, :email_1), (:name_2, :email_2)
```

### PDO Update query
This is an example of a PDO UPDATE query using all available fields:

```PHP
$query = new Query([
    'method' => 'UPDATE',
    'table' => 'users',
    'fields' => ['name', 'email'],
    'where' => 'id = 1',
    'joins' => ['LEFT JOIN orders ON users.id = orders.user_id']
]);
```

The resulting query will be:
```SQL
UPDATE users
SET name = :name, email = :email
LEFT JOIN orders ON users.id = orders.user_id WHERE id = 1
```

### Delete query
This is an example of a DELETE query using all available fields:

```PHP
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

> **Security note:** The `where` value is embedded as a raw SQL fragment, so always use named placeholders (e.g. `id = :id`) and pass the actual values via the binding array when executing the query through the `Database` class. The `order_by` value is validated against a strict pattern that allows only identifiers made of letters, digits, and underscores (optionally qualified with dots), separated by commas and arbitrary whitespace, with optional `ASC`/`DESC` keywords — any other characters will throw an `InvalidArgumentException`.

# Database class
The Database class provides a comprehensive set of methods for performing essential database operations such as select, insert, update, and delete. It also includes advanced features like transaction management, record counting, and inserting many records, making it easier to handle both simple and complex database tasks efficiently.


### Creating a database object
The classes **Sql**, **Mysql**, **Postgres** and **Sqlite** extends of the parent class **Database** who own the methods that the two child classes have in commun.

To create a object is needed to specify some properties:
* **serverName**: The name or IP address of the server.
* **userName**: The userName that is going to be used for the operations.
* **password**: The password of the username.
* **DB**: The database that you are going to use. (optional)
* **codification**: The codification you want to use. (optional)

```php
$properties = [
    "serverName" => "localhost",
    "username" => "root",
    "password" => "",
    "DB" => "your_database",
    "codification" => "utf8"
];

$mysql_object = new Mysql($properties);
$sql_object = new Sql($properties);
$sqlite_object = new Sqlite($properties);
$postgres_object = new Postgres($properties);
```

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
```PHP
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
```PHP
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
**This feature is supported in all CRUD methods (including multi-row inserts via `insert()`), but not in plain query helpers like `executePlainQuery()` or `plainSelect()`.**

---

### Executing plain query
If you dont want the especific methods that are below, you can execute a query with this method, which has this parameters:

* **query**: This can be either a string containing the SQL query, or an instance of the *Query* class.

* **data**: There are the variables of the query in a asociative array, this field is not required.

This method returns a boolean value indicating success, or throws an exception if an error occurs.

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
*In the query a variable need to go behind ":"*

---

### Execute plain Select query
This method works similarly to the previous one, but is specifically designed for SELECT clauses:

* **query**: This can be either a string containing the SQL query, or an instance of the *Query* class.

* **data**: There are the variables of the query in a asociative array, this field is not required.

It returns the associative array of the query results, or throws an exception if an error occurs.

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

The `select` and `selectOne` methods allow you to retrieve records from the database using a `Query` class object. The `select` method returns all matching records, while `selectOne` returns only a single record.

**Example using `select`:**
```php
$query = new Query([
    'method' => 'SELECT',
    'fields' => ['id', 'name'],
    'table' => 'users',
    'where' => 'id = :userId'
]);

try {
    $result = $database->select($query, ["userId" => 2]);
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

**Example using `selectOne`:**
```php
$query = new Query([
    'method' => 'SELECT',
    'fields' => ['id', 'name'],
    'table' => 'users',
    'where' => 'id = :userId'
]);

try {
    $result = $database->selectOne($query, ["userId" => 2]);
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```
---

### Insert statement
Works like the **select** methods, but inserts one or more records into the specified table. The method automatically detects if you are inserting a single record or multiple records.

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
Updates records in the specified table. You must provide the table, the data to update, and the `where` condition.

Always supply WHERE values via `$whereData` instead of inline in the SQL string to prevent SQL injection. Keys in `$whereData` must not overlap with column names in `$data`. Note: positional placeholders (`?`) are not supported in `$where` for `update()` — use named placeholders (e.g. `id = :id`) instead.

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
Deletes records from the specified table. You can specify the `where` condition, `order_by`, and `limit` if needed.

Always use named placeholders in `$where` and supply the actual values in the binding array to avoid SQL injection:

```php
try {
    $deleted = $database->delete('users', 'id = :id', ['id' => 2]);
    echo "Rows deleted: " . $deleted;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

The `order_by` parameter must be a comma-separated list of column identifiers (letters, digits, underscores, and dots), using only whitespace between tokens, with each column optionally followed by `ASC` or `DESC` (e.g. `'created_at DESC'`). Passing any other characters will throw an `InvalidArgumentException`.

To delete all records from a table (optionally with limit and order), use `deleteAll`:

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
Returns the number of records that match a condition. Always use named placeholders in `$where` and supply the actual values in the binding array to avoid SQL injection:

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
You can execute multiple operations inside a transaction using `executeTransaction`:

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

This project is licensed under the MIT License. See the LICENSE file




