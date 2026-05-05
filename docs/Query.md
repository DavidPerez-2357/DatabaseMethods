# Query

The `Query` class builds SQL query strings from PHP. It supports two equivalent styles:

- **Fluent API** - static factory methods with chainable setters; IDE-friendly and readable.
- **Array constructor** - the original API, fully supported and interchangeable with the fluent API.

Both styles produce identical SQL. Cast a `Query` object to string with `echo` / concatenation, or call `getQuery()` explicitly.

> [!TIP]
> Prefer `getQuery()` over string casting when you need reliable error handling. String casting catches build errors internally and returns an empty string with an `E_USER_WARNING`; `getQuery()` propagates the exception to the caller.

&emsp;

## Fluent API reference

| Factory method | Description |
|---|---|
| `Query::select($fields)` | Start a SELECT query |
| `Query::insert($table, [$fields])` | Start an INSERT query |
| `Query::update($table, [$fields])` | Start an UPDATE query |
| `Query::delete($table)` | Start a DELETE query |
| `Query::quote($identifier, [$dialect])` | Quote a single identifier with the given dialect (ANSI by default) |

| Chainable setter | Applies to | Description |
|---|---|---|
| `->from($table)` / `->table($table)` | All | Set the target table |
| `->fields($fields)` | SELECT, INSERT, UPDATE | Set the column list |
| `->where($expr)` | SELECT, UPDATE, DELETE | Set the WHERE clause |
| `->join($join)` | SELECT, UPDATE | Append one JOIN clause (raw SQL expression) |
| `->joins($joins)` | SELECT, UPDATE | Replace all JOINs at once |
| `->innerJoin($table, $condition)` | SELECT, UPDATE | Append an INNER JOIN clause |
| `->leftJoin($table, $condition)` | SELECT, UPDATE | Append a LEFT JOIN clause |
| `->rightJoin($table, $condition)` | SELECT, UPDATE | Append a RIGHT JOIN clause |
| `->fullJoin($table, $condition)` | SELECT, UPDATE | Append a FULL JOIN clause |
| `->groupBy($expr)` | SELECT | Set GROUP BY |
| `->having($expr)` | SELECT | Set HAVING |
| `->orderBy($expr)` | SELECT, DELETE | Set ORDER BY |
| `->limit($n)` | SELECT, DELETE | Set LIMIT |
| `->offset($n)` | SELECT | Set OFFSET |
| `->setDialect($dialect)` | SELECT | Set SQL dialect for pagination rendering |
| `->valuesCount($n)` | INSERT | Number of rows to insert (default 1) |

&emsp;

## SELECT

**Fluent API:**
```php
$query = Query::select(['id', 'name'])
    ->from('users')
    ->leftJoin('orders o', 'o.user_id = users.id')
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

Result:
```sql
SELECT id, name FROM users
LEFT JOIN orders ON users.id = orders.user_id
WHERE users.active = 1
GROUP BY users.id HAVING COUNT(orders.id) > 0
ORDER BY users.name ASC
LIMIT 10
```

> See the [JOINs](#joins) section below for all available join methods.

### Dialect-aware pagination

By default, SELECT pagination renders as `LIMIT/OFFSET` (MySQL/PostgreSQL/SQLite style).
You can switch rendering using `setDialect()`:

```php
$sql = Query::select(['id'])
    ->setDialect(new SqlServerDialect())
    ->from('users')
    ->orderBy('created_at DESC')
    ->limit(10)
    ->offset(5)
    ->getQuery();
// SELECT id FROM users ORDER BY created_at DESC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY
```

**SQL Server pagination rules:**

| Clause | Generated SQL | ORDER BY required? |
|---|---|---|
| `limit` only | `SELECT TOP n …` | No |
| `offset` only | `… OFFSET n ROWS` | **Yes** - throws `InvalidArgumentException` if absent |
| `limit` + `offset` | `… OFFSET n ROWS FETCH NEXT m ROWS ONLY` | **Yes** - throws `InvalidArgumentException` if absent |

```php
// OK - limit only; SELECT TOP n does not require ORDER BY
$sql = Query::select()->setDialect(new SqlServerDialect())->from('t')->limit(10)->getQuery();
// SELECT TOP 10 * FROM t

// OK - limit + offset with ORDER BY
$sql = Query::select()->setDialect(new SqlServerDialect())
    ->from('t')->orderBy('id ASC')->limit(10)->offset(5)->getQuery();
// SELECT * FROM t ORDER BY id ASC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY

// Throws: offset without ORDER BY is invalid SQL Server syntax
$sql = Query::select()->setDialect(new SqlServerDialect())->from('t')->offset(5)->getQuery();
```

&emsp;

## INSERT

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

`valuesCount()` / `values_to_insert` sets how many rows are prepared (defaults to 1).

Result:
```sql
INSERT INTO users (name, email)
VALUES (:name_0, :email_0), (:name_1, :email_1), (:name_2, :email_2)
```

&emsp;

## UPDATE

**Fluent API:**
```php
$query = Query::update('users', ['name', 'email'])
    ->leftJoin('orders o', 'o.user_id = users.id')
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

Result:
```sql
UPDATE users
LEFT JOIN orders ON users.id = orders.user_id
SET name = :name, email = :email
WHERE id = :id
```

> See the [JOINs](#joins) section below for all available join methods.

&emsp;

## DELETE

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

Result:
```sql
DELETE FROM users
WHERE id = :id
ORDER BY created_at DESC
LIMIT 10
```

> [!WARNING]
> The `where` value is embedded as a raw SQL fragment. Always use PDO placeholders (`id = :id` or `id = ?`) and bind values through `Database`. **Never interpolate user-controlled values directly into WHERE, HAVING, or JOIN strings** - that creates an SQL injection vulnerability.

&emsp;

## JOINs

JOIN clauses can be appended to SELECT and UPDATE queries. Use the typed helpers (`innerJoin`, `leftJoin`, `rightJoin`, `fullJoin`) - each takes a table expression and an ON condition:

```php
$query = Query::select(['users.id', 'users.name', 'r.name AS role'])
    ->from('users')
    ->innerJoin('roles r', 'r.id = users.role_id')
    ->leftJoin('orders o', 'o.user_id = users.id');
```

The generic `join()` method (raw SQL string) is also available for join types not covered by the helpers:

```php
->join('CROSS JOIN config')
```

> [!WARNING]
> `join()` and the array-constructor `joins` key pass values through as raw SQL. Always use PDO placeholders for user-supplied values.

&emsp;

## Identifier validation

**Table names** (`->from()`, `->table()`, `Query::delete()`, etc.):
- Plain identifier: letters, digits, underscores, starting with a letter or underscore (e.g. `users`, `order_items`).
- Optionally schema-qualified: `schema.table` (e.g. `myschema.orders`).
- Quoting, whitespace, aliases, and arbitrary SQL are rejected.

**INSERT / UPDATE column names** (`->fields()`, `Query::insert($table, $fields)`, etc.):
- Plain **unqualified** identifiers (e.g. `email`, `created_at`) and quoted identifiers (e.g. `"order"`, `` `from` ``) are accepted.
- Schema-qualified names like `users.email` are rejected because the column name is used to build PDO placeholder tokens.

**GROUP BY / ORDER BY**:
- One or more plain identifiers (optionally table-qualified), comma-separated.
- `ORDER BY` additionally allows `ASC` / `DESC` per column.
- Function calls, expressions, or subqueries are not accepted.

**WHERE, HAVING, JOIN** - passed through as raw SQL fragments. Use PDO placeholders for any user-supplied values.

&emsp;

## Quoting identifiers

Use `Query::quote($identifier)` or `$db->quote($identifier)` when a table or column name is a reserved word (e.g. `order`, `from`, `group`) or otherwise conflicts with SQL syntax.

`$db->quote()` automatically uses the correct quoting style for the connected database. `Query::quote()` accepts an optional dialect for the same effect; without a dialect it defaults to ANSI double-quotes.

| Driver | Quote style | Example |
|---|---|---|
| MySQL | backtick | `` `order` `` |
| PostgreSQL / SQLite / SQL Server | double-quote | `"order"` |

```php
// Using the database connection (recommended — picks the right style automatically)
$db->quote('order')                        // MySQL  => '`order`'
$db->quote('order')                        // Others => '"order"'

// Using the static helper with an explicit dialect
Query::quote('order', $db->getDialect())   // same as above
Query::quote('order', new MysqlSqlDialect()) // => '`order`'
Query::quote('order')                        // => '"order"' (ANSI default)
```

Quoted identifiers can be used wherever a raw string identifier is accepted — `from()`, select fields, `orderBy()`, `groupBy()`, and also in `insert()`/`update()` field lists:

```php
$q = Query::select([$db->quote('order'), 'name'])
    ->from($db->quote('user'))
    ->orderBy($db->quote('group') . ' ASC');
// MySQL  => SELECT `order`, name FROM `user` ORDER BY `group` ASC
// Others => SELECT "order", name FROM "user" ORDER BY "group" ASC
```

Quoted field names work in `insert()` and `update()` too. The name inside the quote delimiters must be a plain identifier (letters, digits, and underscores only) — identifiers with spaces or dashes such as `"first name"` or `` `user-name` `` are **not** supported in field lists because they cannot be mapped to valid PDO placeholder names. Use `$db->quote()` to get the dialect-correct quoting — the library strips the delimiters when building the PDO placeholder, so the column name appears quoted in the SQL but the binding key is plain:

```php
// INSERT using a reserved-word column name
$orderCol = $db->quote('order');   // MySQL => '`order`', others => '"order"'
$db->insert('orders', [$orderCol => 5, 'name' => 'Alice']);
// MySQL  => INSERT INTO orders (`order`, name) VALUES (:order_0, :name_0)
// Others => INSERT INTO orders ("order", name) VALUES (:order_0, :name_0)

// UPDATE using a reserved-word column name
$db->update('orders', [$orderCol => 6], 'id = :id', [':id' => 1]);
// MySQL  => UPDATE orders SET `order` = :order WHERE id = :id
// Others => UPDATE orders SET "order" = :order WHERE id = :id
```

For schema-qualified names, quote each segment individually:

```php
$table = $db->quote('public') . '.' . $db->quote('order');
// MySQL  => `public`.`order`
// Others => "public"."order"
```

Internal quotes are escaped automatically (`"` → `""`, `` ` `` → ` `` `` ``).
