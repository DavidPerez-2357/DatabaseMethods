# Copilot Coding Agent Instructions: DatabaseMethods

## Project Overview

DatabaseMethods is a lightweight PHP library that cuts database boilerplate down to its essentials. It provides three focused classes: `Query` (SQL builder), `Database` (PDO wrapper), and `PdoParameterBuilder` (named-parameter helper). All of them work without Composer or any external dependency.

> Compatible with PHP **5.4** and above. Supports MySQL, PostgreSQL, SQLite, and SQL Server.

## Tech Stack

| Layer           | Technology                                              |
| --------------- | ------------------------------------------------------- |
| Language        | PHP 5.4+                                                |
| Database access | PDO (built-in PHP extension)                            |
| Drivers         | `Mysql`, `Postgres`, `Sql` (SQL Server), `Sqlite`       |
| Testing         | Custom dependency-free runner (`tests/run.php`)         |
| Package manager | None (plain `require_once`, no Composer)                |

## Getting Started

```bash
# No install step required: just require the entry-point file in your code:
# require_once 'DatabaseMethods.php';

# Run the test suite
php tests/run.php
```

## Detailed Instructions

Consult the following files for in-depth guidelines:

- [instructions/code-quality.md](instructions/code-quality.md): Senior developer mindset, PHP version awareness, performance and security rules.
- [instructions/architecture.md](instructions/architecture.md): Project structure, layered architecture, and key invariants for each class.
- [instructions/conventions.md](instructions/conventions.md): Naming, PHP style, language rules, no external dependencies, and testing.
- [instructions/workflow.md](instructions/workflow.md): Commit messages (gitmoji), pull request titles, and branch naming.
