# Database methods
This file provides two essential classes designed to simplify and streamline your workflow when working with database queries.

- The first class helps you build queries in a cleaner and more organized way, improving readability and maintainability.
<br>
- The second class offers a collection of convenient database methods that let you execute common queries with shorter, more intuitive code.

By using these tools, you'll significantly reduce the time spent writing repetitive query logic, allowing you to focus more on building features and less on boilerplate code.

> **Compatibility:**  
This library is compatible with PHP **version 4.3** and above.

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
    'where' => 'id = 1',
    'order_by' => 'created_at DESC',
    'limit' => 10
]);
```

The resulting query will be:
```SQL
DELETE FROM users
WHERE id = 1
ORDER BY created_at DESC
LIMIT 10
```

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

You can add more keywords, here is an example of use:
```PHP
$data = [
    'id' => '@lastInsertId',
    'name'=> '@randomString',
    'created_at'=> '@currentDateTime',
];

try {
    $database->update('users', $data, 'id = :id');
}catch (Exception $e) {
    echo 'Error:'. $e->getMessage();
}
```
**This feature is supported in all methods except for the "insert many" method.**

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
Is the same as as the **plain select method** but you must give a Query class object in the query param. Here is an example:
```php
$query = new Query(
    'method' => 'SELECT',
    'fields' => ['id', 'name'],
    'table' => 'users',
    'where' => 'id = :userId'
)

try {
    $result = $database->select($query, ["userId" => 2]);
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

---

### Select only one record
Is the same as as the **select method** but return only one record. Here is an example:

```php
$query = new Query(
    'method' => 'SELECT',
    'fields' => ['id', 'name'],
    'table' => 'users',
    'where' => 'id = :userId'
)

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

```php
$data = [
    'name' => 'Michael',
    'email' => 'michael@email.com'
];

try {
    $affected = $database->update('users', $data, 'id = :id', [], ['id' => 5]);
    echo "Rows updated: " . $affected;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

---

### Delete statement
Deletes records from the specified table. You can specify the `where` condition, `order_by`, and `limit` if needed.

```php
try {
    $deleted = $database->delete('users', ['id' => 2], 'id = :id');
    echo "Rows deleted: " . $deleted;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

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
Returns the number of records that match a condition:

```php
try {
    $total = $database->count('users', [], 'active = 1');
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
        $db->update('users', ['active' => 0], 'id = :id', [], ['id' => 2]);
        $db->delete('orders', ['user_id' => 2], 'user_id = :user_id');
    });
    echo "Transaction completed.";
} catch (Exception $e) {
    echo "Transaction error: " . $e->getMessage();
}
```

---

## License

This project is licensed under the MIT License. See the LICENSE file




