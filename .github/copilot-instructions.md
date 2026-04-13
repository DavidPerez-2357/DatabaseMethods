# Copilot Coding Agent Instructions — DatabaseMethods

## Project Overview

DatabaseMethods is a lightweight PHP library that cuts database boilerplate down to its essentials. It provides three focused classes — `Query` (SQL builder), `Database` (PDO wrapper), and `PdoParameterBuilder` (named-parameter helper) — that work without Composer or any external dependency.

> Compatible with PHP **5.4** and above. Supports MySQL, PostgreSQL, SQLite, and SQL Server.

## Tech Stack

| Layer           | Technology                                                     |
| --------------- | -------------------------------------------------------------- |
| Language        | PHP 5.4+                                                       |
| Database access | PDO (built-in PHP extension)                                   |
| Drivers         | `Mysql`, `Postgres`, `Sql` (SQL Server), `Sqlite`              |
| Testing         | Custom dependency-free runner (`tests/run.php`)                |
| Package manager | None — plain `require_once`, no Composer                       |

## Getting Started

```bash
# No install step required — just require the entry-point file in your code:
# require_once 'DatabaseMethods.php';

# Run the test suite
php tests/run.php
```

## Code Quality Standards

### Senior Developer Mindset

When generating or reviewing code for this project, apply the standards of a senior developer:

- **Clean code**: Keep methods small, focused, and easy to understand. Favour readability over cleverness.
- **Well-structured**: Place every piece of code in the correct layer (see Project Structure below). Core logic belongs in `src/`; driver-specific overrides go in `src/drivers/`.
- **Descriptive naming**: Use clear, self-documenting names for variables, methods, classes, and files. Avoid abbreviations unless widely understood (e.g., `id`, `sql`, `db`, `pdo`).
- **Comments**: Add comments only when they explain _why_ something is done, not _what_ it does. Complex logic, non-obvious decisions, or workarounds warrant a brief explanation.
- **Language conventions**: Follow PHP best practices as they apply to the PHP 5.4+ constraint.

### Version Awareness

Always consult `README.md` and `CONTRIBUTING.md` and use features compatible with **PHP 5.4+**. Do **not** suggest patterns, APIs, or syntax introduced in later PHP versions.

| Feature to avoid                         | Introduced in |
| ---------------------------------------- | ------------- |
| Variadic functions (`...$args`)          | PHP 5.6       |
| Scalar type declarations                 | PHP 7.0       |
| Return type hints                        | PHP 7.0       |
| `Throwable` interface                    | PHP 7.0       |
| Null coalescing operator (`??`)          | PHP 7.0       |
| Named arguments                          | PHP 8.0       |
| Match expressions                        | PHP 8.0       |
| Union types / intersection types         | PHP 8.0 / 8.1 |
| Enums                                    | PHP 8.1       |
| `readonly` properties                    | PHP 8.1       |

### Performance & Security

#### Performance

- **Avoid redundant queries**: Design methods so callers do not need to run a SELECT immediately before or after a write.
- **Lazy SQL building**: `Query` builds its SQL string lazily — invalidate the cache (`$this->query = null`) whenever a setter changes state.
- **Fluent API**: New `Query` setter methods must return `$this` to support method chaining.

#### Security

- **SQL injection prevention — identifiers**: Any table name or column name interpolated directly into an SQL string **must** pass through `validateIdentifier()` or `validateUnqualifiedIdentifier()` before use.
- **SQL injection prevention — values**: Always use parameterised queries (pass values as a `$params` array to PDO); never concatenate user-supplied values directly into SQL strings.
- **No secrets in source code**: Never commit credentials, passwords, or other sensitive values.
- **Dependency hygiene**: The library has zero external dependencies by design — do not introduce Composer packages.

## Project Structure

```
DatabaseMethods/
├── DatabaseMethods.php          # Single entry-point — auto-loads all classes below
├── src/
│   ├── Database.php             # PDO wrapper base class (select, insert, update, delete, count, transactions)
│   ├── Query.php                # SQL builder (fluent API + array constructor)
│   ├── PdoParameterBuilder.php  # Static helper for building named PDO parameter arrays
│   └── drivers/
│       ├── Mysql.php            # MySQL driver (extends Database)
│       ├── Postgres.php         # PostgreSQL driver (extends Database)
│       ├── Sql.php              # SQL Server driver (extends Database)
│       └── Sqlite.php           # SQLite driver (extends Database)
├── tests/
│   ├── run.php                  # Custom test runner (no PHPUnit)
│   ├── QueryTests.php           # Unit tests for Query
│   ├── DatabaseTest.php         # Integration tests for Database (uses SQLite)
│   └── PdoParameterBuilderTests.php  # Unit tests for PdoParameterBuilder
├── docs/
│   ├── Query.md                 # Full Query API documentation
│   ├── Database.md              # Full Database API documentation
│   └── PdoParameterBuilder.md   # Full PdoParameterBuilder API documentation
├── README.md
├── CONTRIBUTING.md
└── LICENSE
```

## Architecture & Patterns

### Layered Architecture

Follow this strict layering when adding code:

1. **`Query`** (`src/Query.php`) — Pure SQL-string builder. No PDO, no I/O. Validates identifiers, builds parameterised SQL strings. New query-builder features go here.
2. **`PdoParameterBuilder`** (`src/PdoParameterBuilder.php`) — Static utility. Builds named-parameter arrays and common SQL fragments (equality, set clauses, insert placeholders). No PDO execution.
3. **`Database`** (`src/Database.php`) — Base PDO wrapper. Executes SQL using `Query` objects or raw strings. Handles connection, locking (where applicable), result formatting, and keyword replacement for CRUD sugar. Driver-agnostic logic lives here.
4. **Drivers** (`src/drivers/`) — Thin subclasses of `Database`. Each driver sets up the PDO DSN and may override `$supportedJoins` or connection-specific behaviour. Keep driver classes minimal.

### Entry Point

`DatabaseMethods.php` uses `require_once` to load all classes. Any new source file added to `src/` (or `src/drivers/`) **must** be added to this loader.

### Query Builder — Key Invariants

- The `getQuery()` method builds and caches the SQL string; it is called by `__toString()`.
- Whenever a fluent setter changes state, set `$this->query = null` to invalidate the cache.
- All column/table identifiers that are interpolated into SQL must be validated.
- Both the fluent API (static factory + chained setters) and the array constructor must produce identical SQL for the same logical query.

### Database — Key Invariants

- Public CRUD methods (`select`, `selectOne`, `insert`, `update`, `delete`, `deleteAll`, `count`) are routed through `__call()`, which applies keyword replacement before delegating to the private implementation.
- Parameterised values are always bound via `bindNamedParams()` — never concatenated.
- `executeTransaction(callable $fn)` wraps a callback in a PDO transaction with automatic rollback on exception.

## Coding Conventions

### Naming

- **Classes**: `PascalCase` (e.g., `Database`, `PdoParameterBuilder`, `Mysql`).
- **Methods and variables**: `camelCase` (e.g., `getQuery()`, `$serverName`).
- **Constants**: `UPPER_SNAKE_CASE` (e.g., `IDENTIFIER`).
- **Files**: Match the class name exactly (e.g., `Database.php`, `Sqlite.php`).

### PHP Style

- Opening brace for classes and methods on the **same line** — follow the existing file style.
- Use `array()` syntax, not short array syntax `[]`, to preserve PHP 5.4 compatibility.
- Use `isset()` / `array_key_exists()` for optional keys rather than `??`.
- DocBlocks (`/** ... */`) for all public methods; inline comments with `//` for non-obvious logic.

### Language

- **Code is written in English**: all identifiers (variables, methods, classes, files) must use English.
- **Comments may be written in Spanish or English** — follow the style already present in the file you are editing.

### No External Dependencies

Never add Composer packages or any external library. The entire codebase must be loadable with a single `require_once 'DatabaseMethods.php'`.

## Testing

- The test suite lives in `tests/` and is run with:

  ```bash
  php tests/run.php
  ```

- **`QueryTests.php`** — unit tests for `Query`.
- **`DatabaseTest.php`** — integration tests for `Database` (uses SQLite in-memory).
- **`PdoParameterBuilderTests.php`** — unit tests for `PdoParameterBuilder`.
- The runner exits with code `0` on success and `1` on failure.
- **Every new public method or behaviour change must be accompanied by a corresponding test.**
- Do not use PHPUnit or any external testing framework.

## Commit Messages

This project uses **[gitmoji](https://gitmoji.dev/)** for commit messages. Each commit starts with a relevant emoji followed by a short, concise description.

### Common gitmoji used in this project

| Emoji | Code                  | Use for                              |
| ----- | --------------------- | ------------------------------------ |
| ✨    | `:sparkles:`          | New feature                          |
| 🐛    | `:bug:`               | Bug fix                              |
| 🚑️   | `:ambulance:`         | Critical hotfix                      |
| ♻️    | `:recycle:`           | Refactor code                        |
| 🎨    | `:art:`               | Improve code structure or formatting |
| 🚧    | `:construction:`      | Work in progress                     |
| ⚡️   | `:zap:`               | Improve performance                  |
| 🔥    | `:fire:`              | Remove code or files                 |
| ✏️    | `:pencil2:`           | Fix typos                            |
| 📝    | `:memo:`              | Documentation                        |
| 🔒️   | `:lock:`              | Security fix                         |
| 🔧    | `:wrench:`            | Configuration changes                |
| 📦️   | `:package:`           | Build artifacts or packages          |
| 🗃️    | `:card_file_box:`     | Database or storage changes          |
| ⬆️    | `:arrow_up:`          | Upgrade dependencies                 |
| ⬇️    | `:arrow_down:`        | Downgrade dependencies               |
| ➕    | `:heavy_plus_sign:`   | Add dependency                       |
| ➖    | `:heavy_minus_sign:`  | Remove dependency                    |
| 🚚    | `:truck:`             | Move or rename files                 |
| ✅    | `:white_check_mark:`  | Add or update tests                  |
| 💥    | `:boom:`              | Breaking changes                     |
| 🥅    | `:goal_net:`          | Catch errors                         |
| 🦺    | `:safety_vest:`       | Validation                           |
| 🦖    | `:t-rex:`             | Backwards compatibility              |

### Format

```
<gitmoji> <Short imperative description>
```

### Examples from this project

```
✨ Add fluent API to Query class
🐛 Fix identifier validation for qualified column names
🗃️ Add PdoParameterBuilder helper for named params
♻️ Refactor Database __call routing
📝 Document PdoParameterBuilder methods
✅ Add integration tests for Sqlite driver
🔒 Prevent SQL injection in identifier interpolation
```

Keep messages **short and concise** — describe _what_ was done, not _how_.

## Pull Request Titles

Pull Request titles on GitHub follow the **same gitmoji convention** as commit messages.

### Format

```
<gitmoji> <Short imperative description>
```

### Examples

```
✨ Add JOIN support to Query fluent API
🐛 Fix NULL handling in selectOne
♻️ Refactor transaction rollback logic
📝 Update Database documentation
✅ Add QueryTests for edge-case WHERE clauses
```

## Branch Naming

Use a prefix that reflects the type of work, followed by a **concise** kebab-case name.

| Prefix      | Use for                       |
| ----------- | ----------------------------- |
| `feature/`  | New functionalities           |
| `bugfix/`   | Bug fixes                     |
| `hotfix/`   | Urgent production fixes       |
| `refactor/` | Code refactoring              |
| `docs/`     | Documentation updates         |
| `test/`     | Tests and test improvements   |
| `chore/`    | Maintenance and general tasks |

### Format

```
<prefix>/<concise-name>
```

### Examples

```
feature/add-join-fluent-api
bugfix/null-handling-select-one
hotfix/identifier-injection-fix
refactor/query-cache-invalidation
docs/pdoparameterbuilder-readme
test/database-transaction-tests
chore/cleanup-driver-constructors
```
