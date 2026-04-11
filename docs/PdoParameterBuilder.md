# PdoParameterBuilder

`PdoParameterBuilder` is a static utility class for generating PDO named-parameter maps and common SQL fragments. All methods are stateless - no object instantiation required.

> [!NOTE]
> Identifier validation in relevant methods relies on `Query::validateUnqualifiedIdentifier()`. Column names must be plain unqualified SQL identifiers (letters, digits, underscores, starting with a letter or underscore). Qualified names like `users.email` are rejected.

&emsp;

## buildNamedParams

Builds a PDO named-parameter map from a column -> value associative array. Column names are validated as plain SQL identifiers.

**Signature:**
```php
PdoParameterBuilder::buildNamedParams(array $data, $prefix = '')
```

| Parameter | Type | Description |
|---|---|---|
| `$data` | `array` | Associative array of `column => value` pairs |
| `$prefix` | `string` | Optional prefix for placeholder names (default `''`) |

**Returns:** `array` - associative array mapping `':prefix_col' => value`.

**Throws:** `InvalidArgumentException` if any column name fails identifier validation.

**Example:**
```php
$params = PdoParameterBuilder::buildNamedParams(['name' => 'Alice', 'age' => 30]);
// => [':name' => 'Alice', ':age' => 30]

$params = PdoParameterBuilder::buildNamedParams(['name' => 'Alice', 'age' => 30], 'set_');
// => [':set_name' => 'Alice', ':set_age' => 30]
```

&emsp;

## buildInsertParams

Builds the flat PDO named-parameter map for a multi-row INSERT from an array of row arrays. Each key follows the form `:col_N` where `N` is the zero-based row index. Values are always read in the first row's column order, so rows with the same key set but different insertion order work correctly.

**Signature:**
```php
PdoParameterBuilder::buildInsertParams(array $rows)
```

| Parameter | Type | Description |
|---|---|---|
| `$rows` | `array` | Non-empty array of associative arrays; each row must have the same key set as the first row |

**Returns:** `array` - flat params map, e.g. `[':name_0' => 'Alice', ':age_0' => 30, ':name_1' => 'Bob', ':age_1' => 25]`.

**Throws:** `InvalidArgumentException` if `$rows` is empty, any row is not an array, any row's key set differs from the first row, or any column name fails identifier validation.

**Example:**
```php
$params = PdoParameterBuilder::buildInsertParams([
    ['name' => 'Alice', 'age' => 30],
    ['name' => 'Bob',   'age' => 25],
]);
// => [':name_0' => 'Alice', ':age_0' => 30, ':name_1' => 'Bob', ':age_1' => 25]
```

Pair with `buildInsertPlaceholders()` to construct the full INSERT SQL:
```php
$fields = ['name', 'age'];
$rows   = [['name' => 'Alice', 'age' => 30], ['name' => 'Bob', 'age' => 25]];

$groups = PdoParameterBuilder::buildInsertPlaceholders($fields, count($rows));
$params = PdoParameterBuilder::buildInsertParams($rows);

// INSERT INTO users (name, age) VALUES (:name_0, :age_0), (:name_1, :age_1)
```

&emsp;

## buildInsertPlaceholders

Builds the per-row placeholder groups for a multi-row INSERT `VALUES` clause. Column names are validated as plain SQL identifiers.

**Signature:**
```php
PdoParameterBuilder::buildInsertPlaceholders(array $fields, $rowCount)
```

| Parameter | Type | Description |
|---|---|---|
| `$fields` | `array` | Non-empty array of column names |
| `$rowCount` | `int` | Number of rows to prepare (must be >= 1) |

**Returns:** `array` - array of row-group strings, one per row.

**Throws:** `InvalidArgumentException` if `$fields` is empty, `$rowCount < 1`, or any field name fails identifier validation.

**Example:**
```php
$groups = PdoParameterBuilder::buildInsertPlaceholders(['name', 'email'], 2);
// => ['(:name_0, :email_0)', '(:name_1, :email_1)']

$sql = 'INSERT INTO users (name, email) VALUES ' . implode(', ', $groups);
// => INSERT INTO users (name, email) VALUES (:name_0, :email_0), (:name_1, :email_1)
```

&emsp;

## buildValues

Builds a PDO named-parameter map from an indexed list of values. The input array is re-indexed before placeholder names are assigned, so non-contiguous keys are handled correctly.

**Signature:**
```php
PdoParameterBuilder::buildValues(array $values, $prefix = '')
```

| Parameter | Type | Description |
|---|---|---|
| `$values` | `array` | Indexed array of values |
| `$prefix` | `string` | Optional prefix for placeholder names (default `''`) |

**Returns:** `array` - associative array mapping `':prefix_N' => value`.

**Example:**
```php
$params = PdoParameterBuilder::buildValues([10, 20, 30], 'id_');
// => [':id_0' => 10, ':id_1' => 20, ':id_2' => 30]

// Useful for IN clauses:
$ids    = [3, 7, 42];
$params = PdoParameterBuilder::buildValues($ids, 'id_');
$keys   = implode(', ', array_keys($params));   // :id_0, :id_1, :id_2
$sql    = "SELECT * FROM users WHERE id IN ({$keys})";
```

&emsp;

## buildSetClause

Builds the SQL `SET` fragment for an UPDATE statement from an array of column names. Column names are validated as plain SQL identifiers.

**Signature:**
```php
PdoParameterBuilder::buildSetClause(array $fields)
```

| Parameter | Type | Description |
|---|---|---|
| `$fields` | `array` | Non-empty array of column names |

**Returns:** `string` - comma-separated `col = :col` fragment.

**Throws:** `InvalidArgumentException` if `$fields` is empty or any name fails identifier validation.

**Example:**
```php
$set = PdoParameterBuilder::buildSetClause(['name', 'email']);
// => 'name = :name, email = :email'

$sql = "UPDATE users SET {$set} WHERE id = :id";
// => UPDATE users SET name = :name, email = :email WHERE id = :id
```
