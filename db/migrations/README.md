# Database migrations

A schema change is made in two places (`docs/maintenance-plan.md`, section 6): a new numbered file in this folder, which changes an existing database, and the same change in `db/schema.sql`, which builds a new database. Run a migration once, on a database created before the change:

```sql
USE community_issues;
SOURCE db/migrations/002_short_name.sql;
```

| File | Change | Status |
|---|---|---|
| `001_add_issue_priority.sql` | issue priority column (FR8), added on 28 September 2026 | Retired in v1.1 (#12), after every member confirmed that their database was built from the current `schema.sql`, which has had the column since dedcc42 |

A retired file stays in the Git history. To restore 001:

```
git show Version.1:db/migrations/001_add_issue_priority.sql > db/migrations/001_add_issue_priority.sql
```

The next migration is 002.
