# PRODUCTION-RECOVERY-07 — cPanel Terminal Checklist (Deployment + Storage Maintenance)

> Target: `/home/pardisa2/bots` on shared hosting (IP `80.191.92.187`, user `pardisa2`).
> SSH from the local machine is **not reachable** — single authorized attempt on port 22
> timed out (2026-11-06 ~10:35 IRST). Per mission rules: no port scanning, no bypass.
> Run everything below from **cPanel → Terminal** (or an SSH route that does work).
>
> Approved target commit: **`0b8f990d`** on branch **`15esfand1404`**
> (local = GitHub = `0b8f990d`). Previous production commit was `d69ef082`.

---

## Phase 1 — Deployment (1.2 / 1.4 / 1.5 / 1.6)

### 1. Server state + tooling (1.2 — read-only)

```bash
cd /home/pardisa2/bots
git rev-parse HEAD            # what is production running right now?
git remote -v                 # must point at github.com/saber13812002/bot-generator-bale-telegram-laravel-10.git
git status --short            # must be empty (clean tree)
git branch --show-current     # must be 15esfand1404
php -v
composer --version
php artisan --version
df -h .
df -i .
quota -s 2>/dev/null || true
du -sh storage/logs storage/framework/cache data 2>/dev/null
du -sh storage/framework/cache data 2>/dev/null
du -sh storage/framework/sessions 2>/dev/null
du -sh storage/app 2>/dev/null
```

**Stop and report if:** tree is dirty, remote is wrong, or HEAD contains commits
we did not review (expected history: `… d69ef082 → 0b8f990d`).

### 2. Deploy gate (1.4)

All must be true before continuing:
- [ ] tree clean, branch `15esfand1404`, remote correct
- [ ] disk/quota has room (see `df -h` / `quota -s`)
- [ ] local pre-deploy tests were green (done: 779 passed / 4559 assertions / 0 failed)
- [ ] managed backup note: **do not create a new full backup** — the hosting provider's
      backup mechanism is in charge. If cPanel shows "last successful backup", just record
      its date in the report (read-only).

### 3. Deploy (1.5)

```bash
cd /home/pardisa2/bots
git fetch origin
git checkout 15esfand1404
git pull --ff-only origin 15esfand1404

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

php artisan migrate:status     # DO NOT migrate unless a migration is unapplied AND
                               # reviewed. If pending: stop, report, await owner approval.

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> Note: the deploy uses `*:cache` (not `cache:clear`) per mission rule.
> If `config:cache` fails because of an env var, fix the env (do not print it).

### 4. Post-deploy verification (1.6)

```bash
git rev-parse HEAD             # must equal 0b8f990d...
git status --short             # must be empty

# app up
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/   # or the site's health URL

# no new errors since deploy
tail -n 50 storage/logs/laravel.log

php artisan schedule:list | grep storage
# expected: 30 3 * * *  storage:cleanup
```

Confirm: no real messages were sent during any of the above (only artisan
read/schedule/config commands were run — nothing sends messages).

---

## Phase 2 — Storage Maintenance (2.2 / 2.3 / 2.4 / 2.5)

### 5. Cron sanity (2.2 — read-only, do not duplicate)

```bash
crontab -l
command -v php
```

Expected: exactly **one** per-minute line like
`* * * * * cd /home/pardisa2/bots && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1`
(adjust php path to what `command -v php` returns).
If a second `schedule:run` entry exists → stop and report (cron conflict).

### 6. Dry-run on server (2.3 — nothing is deleted)

```bash
cd /home/pardisa2/bots
php artisan storage:cleanup --dry-run
```

Record in the report:
- expired cache entries count (per store)
- stale sessions count (older than 2 days)
- `laravel.log` size and whether rotation would trigger (≥ 50 MB)
- dated logs older than 30 days that would be deleted (file names)
- estimated bytes freed

**Stop if** the dry-run lists anything unexpected (user uploads, `storage/app` files,
active cache entries, or anything outside `storage/framework/cache`,
`storage/framework/sessions`, `storage/logs/laravel*.log`).

### 7. Real cleanup (2.4) — only if dry-run was sane

```bash
php artisan storage:cleanup
```

Safety notes built into the command (no manual override needed):
- expired **file-cache** entries only — active and `forever` entries survive
- stale **file sessions** only (older than 2 days)
- log rotation is **skipped automatically** when free disk space < 2× the log size
  (copytruncate needs ~2× headroom; the command prints
  `Log rotation SKIPPED: free disk space is below 2x the log size…`).
  In that case free space manually first (delete nothing outside policy — report instead),
  then re-run.
- `--dry-run` flag exists for re-checking; `--session-days`, `--log-days`,
  `--log-max-mb`, `--log-backups` are available if the owner wants to tune retention.

### 8. Results + proof (2.5)

```bash
# before/after — run the same block before step 7 and after it, paste both into the report
df -h .
df -i .
quota -s 2>/dev/null || true
du -sh storage/logs storage/framework/cache data 2>/dev/null
du -sh storage/framework/cache data 2>/dev/null
du -sh storage/framework/sessions 2>/dev/null
du -sh storage/app 2>/dev/null   # must be UNCHANGED (user files out of scope)

# re-dry-run: must report 0 deletable items now
php artisan storage:cleanup --dry-run

# scheduler knows the job
php artisan schedule:list | grep storage

# timezone alignment (03:30 in which timezone?)
php -r "echo date_default_timezone_get(), PHP_EOL;"
tail -n 1 /etc/timezone 2>/dev/null || cat /etc/sysconfig/clock 2>/dev/null || true
```

**Prove a real cron execution happened** (temp probe — delete afterwards):

```bash
# safe probe: one-time file the scheduler can touch; removed right after
touch /home/pardisa2/bots/storage/logs/sched-probe.txt
echo "probed at $(date -Iseconds)" >> /home/pardisa2/bots/storage/logs/sched-probe.txt
# wait up to ~90 seconds for the next minute tick, then:
grep "Running scheduled job" /home/pardisa2/bots/storage/logs/laravel.log | tail -5
# OR the simplest real evidence: after 03:30 (next day) re-run the dry-run and
# confirm "Deleted … stale session files" lines from the scheduled run in the log:
grep "storage:cleanup" /home/pardisa2/bots/storage/logs/laravel.log | tail -5
rm -f /home/pardisa2/bots/storage/logs/sched-probe.txt
```

---

## Stop conditions (report, do not force)

1. dirty server tree or unreviewed commits → stop, report
2. remote/branch mismatch → stop, report
3. insufficient disk to deploy safely → stop, report
4. unreviewed pending migration → stop, report (owner approves)
5. dry-run targets unexpected files → stop, report
6. any command that looks like it will delete user data → stop, report
7. cron conflict (duplicate `schedule:run`) → stop, report
8. app down after deploy → stop, report

## Status legend

- **VERIFIED** — verified with real execution
- **DEPLOYED** — production is at the approved commit and healthy
- **PARTIALLY COMPLETE** — some steps done, remainder listed
- **BLOCKED** — cannot be executed (e.g., no SSH route; use this cPanel checklist)
- **FAILED** — executed and failed (evidence attached)
