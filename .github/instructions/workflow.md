# Workflow: Commits, Pull Requests, and Branches

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

Keep messages **short and concise**: describe _what_ was done, not _how_.

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
