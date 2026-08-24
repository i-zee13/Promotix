# Local SQL dumps (DigitalOcean / Hostinger import bypass)

phpMyAdmin / DO panel often caps uploads at **2MB**. Put gzipped dumps here and import via Artisan instead.

## Files

- `promotix-db-2026-08-24-part1.sql.gz` (~2.0 MB)
- `promotix-db-2026-08-24-part2.sql.gz` (~2.9 MB)

**Do not** put live DB dumps in `public/` — they contain user emails, tokens, and customer data.

## On the server

```bash
# After git pull / deploy (files must be present under database/dumps/)
php artisan db:import-dump --parts --force

# Or via seeder
php artisan db:seed --class=LocalDumpSeeder --force
```

Requires `mysql` and `gunzip` on the droplet (default on most DO images).

## If dumps are not in git

```bash
scp database/dumps/*.sql.gz user@your-droplet:/var/www/your-app/database/dumps/
```
