# Database upgrades

Hugin detects database migration state on every dynamic request, but it never runs schema changes from the web process. When an upgrade is required, database-backed routes return `503 Service Unavailable`; static assets remain available.

## Normal upgrade

1. Put the deployment behind maintenance handling if applicable and take a tested database backup.
2. Deploy the new Hugin code.
3. Inspect the state:

   ```bash
   php bin/hugin-db status
   ```

4. Review the ordered pending list, then acknowledge the backup and run it:

   ```bash
   php bin/hugin-db migrate --backup-confirmed
   ```

5. Run `php bin/hugin-db status` again. A successful upgrade reports `current`, after which web requests resume automatically.

The runner takes a MySQL/MariaDB advisory lock, checks filenames and SHA-256 checksums, and stops at the first error. MySQL DDL may commit implicitly, so a failed migration is not automatically rolled back or retried.

## Failed or interrupted migration

Do not edit the migration file or its ledger row. Inspect the CLI error, database server logs, and actual schema, restore the backup when appropriate, and correct the underlying problem. After confirming that retrying the SQL is safe, use the same explicit recovery command for a `failed` migration or an interrupted migration left in `running` state:

```bash
php bin/hugin-db migrate --backup-confirmed --retry NNN
```

The web interface remains unavailable while a migration is `running` or `failed`, or when a checksum does not match.

## Adopting an existing installation

An older installation can have the current schema but no `schema_migrations` ledger. Back it up, deploy the current code, and run:

```bash
php bin/hugin-db status
php bin/hugin-db adopt --yes
php bin/hugin-db status
```

Adoption is CLI-only. It verifies each existing migration independently from tables, columns, indexes, constraints, defaults, removed legacy objects, and migrated plugin identifiers. Verified effects are recorded as applied; missing effects remain pending for `migrate`. If only part of a migration's effects is present, adoption makes no ledger changes and reports the migration for manual review.

Fresh installations imported from `database.sql` already contain a complete ledger and do not need adoption.

## CLI reports a missing PDO driver

The migration command uses the command-line PHP runtime, which can load a different extension set from PHP-FPM or Apache. Check it with:

```bash
php --ini
php -r 'print_r(PDO::getAvailableDrivers());'
```

The output must include `mysql`. On Fedora/RHEL, install `php-mysqlnd`; on Debian/Ubuntu, install the `php-mysql` package matching the active PHP version. Then verify the driver again. Restart PHP-FPM or Apache as well if the web runtime uses the same package installation.
