# DatabaseMethods

A lightweight PHP library that cuts database boilerplate down to its essentials — two focused classes that let you write less and do more.

> Compatible with PHP **5.4** and above. MySQL, PostgreSQL, SQLite, and SQL Server supported.

---

## Installation & Usage

Download or clone the repository and require the single entry-point file — it automatically loads every module:

```php
require_once 'DatabaseMethods.php';
```

No Composer, no external dependencies.

---

## What's inside

### [`Query`](docs/Query.md) — SQL builder

Build any SELECT, INSERT, UPDATE, or DELETE with a clean fluent API (or the classic array constructor):

```php
$query = Query::select(['id', 'name'])
    ->from('users')
    ->where('active = 1')
    ->orderBy('name ASC')
    ->limit(20);
```

→ `SELECT id, name FROM users WHERE active = 1 ORDER BY name ASC LIMIT 20`

[Full Query documentation →](docs/Query.md)

---

### [`Database`](docs/Database.md) — PDO wrapper

Connect once, then select, insert, update, delete, count, and run transactions with no boilerplate:

```php
$db = new Mysql(['serverName' => 'localhost', 'username' => 'root', 'password' => '', 'DB' => 'mydb']);

$users  = $db->select($query, ['active' => 1]);
$lastId = $db->insert('users', ['name' => 'Alice', 'email' => 'alice@example.com']);
$rows   = $db->update('users', ['name' => 'Bob'], 'id = :id', ['id' => $lastId]);
$db->executeTransaction(function($db) {
    $db->delete('orders', 'user_id = :id', ['id' => 5]);
    $db->update('users', ['active' => 0], 'id = :id', ['id' => 5]);
});
```

[Full Database documentation →](docs/Database.md)

---

## License

MIT — see [LICENSE](LICENSE).
