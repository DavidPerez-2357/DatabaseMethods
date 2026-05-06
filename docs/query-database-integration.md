# Query–Database Integration

> **Introduced in**: this feature branch  
> **Requires**: a `Database` instance obtained via one of the driver helpers (`Sqlite`, `Mysql`, `Postgres`, or `Sql`).

## Overview

`Database::createQuery()` returns a blank `Query` that is **linked to the database connection**. You can then chain any of the four factory methods — `select()`, `insert()`, `update()`, `delete()` — together with the fluent setter API, and finally call `run()` to execute the query and receive the result.

```php
// SELECT – returns an array of associative arrays (or JSON when json_encode mode is on)
$rows = $db->createQuery()
    ->select(['id', 'name'])
    ->from('users')
    ->where('active = 1')
    ->run();

// INSERT – returns the last-insert ID (int)
$id = $db->createQuery()
    ->insert('users', ['name', 'email'])
    ->run(['name' => 'Alice', 'email' => 'alice@example.com']);

// UPDATE – returns affected-row count (int)
$n = $db->createQuery()
    ->update('users', ['name'])
    ->where('id = :id')
    ->run(['name' => 'Bob', 'id' => 1]);

// DELETE – returns affected-row count (int)
$n = $db->createQuery()
    ->delete('users')
    ->where('id = :id')
    ->run([':id' => 5]);
```

## How It Works

### `Database::createQuery()`

Returns a blank (no-method-set) `Query` instance that holds a reference back to the `Database` and is pre-configured with the driver's SQL dialect (backticks for MySQL, ANSI double-quotes for everything else, `TOP`/`OFFSET-FETCH` for SQL Server).

```php
$query = $db->createQuery(); // blank Query, linked to $db
```

### `Query::setDatabase(Database $db)`

Links an independently-constructed `Query` to a `Database`. You only need this when you build a `Query` object outside of `createQuery()`:

```php
$query = Query::select(['id'])->from('users');
$query->setDatabase($db);
$rows = $query->run();
```

### `Query::run(array $data = [])`

Executes the query against the linked database. `$data` contains the PDO parameter bindings.

| Query type | `$data` contents | Return value |
|------------|-----------------|--------------|
| `SELECT`   | WHERE bindings (named or positional) | `array` of rows (or JSON string) |
| `INSERT`   | Single row (`['col' => val, ...]`) **or** list of rows | `int` — last-insert ID (single row) or `0` (multi-row) |
| `UPDATE`   | SET values **and** WHERE bindings in one flat array | `int` — affected-row count |
| `DELETE`   | WHERE bindings (named or positional) | `int` — affected-row count |

## Static Factory Methods Still Work

The existing `Query::select()`, `Query::insert()`, `Query::update()`, `Query::delete()` static calls are unchanged — they create a fresh, unlinked `Query` and return it for SQL building without execution:

```php
$sql = (string) Query::select(['id', 'name'])
    ->from('users')
    ->where('active = 1')
    ->limit(10);
// => "SELECT id, name FROM users WHERE active = 1 LIMIT 10"
```

## UPDATE: splitting SET and WHERE bindings

For `UPDATE` queries, `run()` uses the field list set on the Query to determine which keys in `$data` are **SET** values and which are **WHERE** bindings. All remaining keys (those not in the field list) are treated as WHERE bindings.

```php
// Fields: ['name']  → SET   key: 'name'
// All others        → WHERE keys: 'old_name'
$n = $db->createQuery()
    ->update('users', ['name'])
    ->where('name = :old_name')
    ->run(['name' => 'New Name', 'old_name' => 'Old Name']);
```

> **Note**: If a column name was quoted when added to the field list (e.g. `'"order"'`), pass its *unquoted* form as the key in `$data` (e.g. `'order'`).

## INSERT: multi-row batches

Pass a sequential list of associative arrays to insert multiple rows in a single statement:

```php
$db->createQuery()
    ->insert('users', ['name', 'email'])
    ->run([
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob',   'email' => 'bob@example.com'],
    ]);
```

For multi-row inserts `run()` returns `0` (last-insert ID is undefined for batch operations across all drivers).

## Error Handling

- Calling `run()` on a `Query` that has no linked database throws `RuntimeException`.
- Calling `run()` before any factory method (no method set) throws `InvalidArgumentException`.
- All existing PDO and validation errors propagate unchanged.
