# PRODUCTION-STABILIZATION-06 — Server-Side Operational Checklist

> Target host: `/home/pardisa2/bots` on shared hosting (IP `80.191.92.187`, user `pardisa2`).
> This checklist covers everything that requires **shell access on the production server**.
> Every command is safe to run read-only first; destructive steps are explicitly marked.
> All code fixes are already committed & pushed: branch `15esfand1404`, HEAD `d6666d8f`
> (code fixes in `88fee8a8`, verification scripts in `d6666d8f`).

---

## 0. Access

- SSH from this machine was **not reachable** (12 candidate ports + 80/443 all closed from our
  network). Run the following **from the hosting panel's terminal (cPanel → Terminal)** or
  from a machine that can reach the SSH endpoint. If the panel terminal is used, all paths
  below start from the account home (`/home/pardisa2`).

---

## Phase 1 — Disk Quota Stabilization

### 1.1 Measure current usage (read-only)

```bash
cd /home/pardisa2/bots

# Total usage per top-level dir (find the big consumers)
du -sh ./* 2>/dev/null | sort -rh | head -20

# Storage subdirs (cache / sessions / logs are the usual offenders)
du -sh storage/framework/cache/data 2>/dev/null
du -sh storage/framework/sessions 2>/dev/null
du -sh storage/framework/views 2>/dev/null
du -sh storage/logs 2>/dev/null
du -sh storage 2>/dev/null

# Account-level disk usage (shared hosting quota)
du -sh /home/pardisa2 2>/dev/null
quota -s 2>/dev/null || true
df -h . 2>/dev/null

# inode usage (many small log files can exhaust inodes)
find /home/pardisa2/bots/storage -type f | wc -l
df -i . 2>/dev/null

# The log that was exploding
ls -lh storage/logs/
```

### 1.2 Verify the new cleanup command works (DRY RUN FIRST)

```bash
cd /home/pardisa2/bots
php artisan storage:cleanup --dry-run
```

Expected: a plan table showing how many cache files / sessions / log bytes **would** be
removed, with **no files actually deleted**. Exit code 0.

### 1.3 Perform the controlled cleanup (only after dry-run looks sane)

```bash
php artisan storage:cleanup
```

The command is conservative by design:
- only **expired** file-cache entries (respects each store's TTL),
- only **stale** sessions (> 72h without access),
- log rotation via copytruncate: keeps `laravel.log` under 50 MB with 5 dated backups,
  never touches active cache or DB data.

Re-run `du -sh storage/*` after and confirm space was reclaimed.

### 1.4 Verify the daily 03:30 schedule + minute cron

```bash
# The Laravel scheduler must be driven by a *per-minute* cron entry
crontab -l

# Expected line (adjust PHP path with: which php / /usr/local/bin/php -v):
# * * * * * cd /home/pardisa2/bots && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1

# Confirm storage:cleanup is in the schedule at 03:30
php artisan schedule:list | findstr storage
# or on unix: php artisan schedule:list | grep storage
```

Expected: `storage:cleanup` listed for `30 3 * * *`. It intentionally has **no**
`withoutOverlapping`/`onOneServer` — those use the cache, which fails when the disk is
full (that is exactly the failure mode this command fixes).

---

## Phase 5 — Operational Errors (owner / channel-provider action)

| # | Error (from production log) | Classification | Required action |
|---|---|---|---|
| 1 | RSS channel **#3** → token rejected (403) on send to channel | **OPERATIONAL — channel owner** | Ask the channel owner for a **fresh bot token** with admin rights on that channel; update it in `rss_channels` (or via admin panel). No code change needed. |
| 2 | Bale chat `5744594331` → `permission_denied` | **OPERATIONAL — platform** | The Bale bot lacks send permission for that chat/user (user blocked the bot, or chat is a group the bot is not admin of). Either the user re-adds the bot, or (for groups) the bot must be made admin. No code change; the log line is now properly recorded and non-fatal. |

Both are **data/provider-side** issues — cannot be fixed from code or from this machine.

---

## Phase 7 — Deploy `88fee8a8` → `d6666d8f` to production

### 7.1 Check what production is currently running

```bash
cd /home/pardisa2/bots
git rev-parse HEAD
git status --short
```

Compare the output with `d6666d8f` (current branch HEAD on GitHub). If it is behind
(likely — production may still predate `88fee8a8`), deploy:

### 7.2 Deploy (follow `deploy_commands.sh`)

```bash
cd /home/pardisa2/bots
git fetch origin
git checkout 15esfand1404
git pull --ff-only origin 15esfand1404

composer install --no-dev --optimize-autoloader --no-interaction

php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan migrate --force
php artisan config:cache
php artisan route:cache

# Verify HEAD now matches GitHub
git rev-parse HEAD   # must equal d6666d8f...
```

### 7.3 Post-deploy verification (real, not mock)

```bash
# 1. Storage cleanup runs cleanly after deploy
php artisan storage:cleanup --dry-run

# 2. Scheduler is healthy (minute cron) — check heartbeat
php artisan pipeline:status

# 3. AI provider state after next hourly health-check
php artisan ai:health-check
php artisan tinker --execute="echo App\Models\AiProvider::all()->toJson();\n"

# 4. One API: token is DEAD (verified 403 from outside). Until the new token is
#    provided, the circuit breaker in OneApiTranslationService short-circuits the
#    dead endpoint after the first 4xx and falls through to the LLM chain.
#    When the new ONE_API_API_TOKEN arrives: set it in .env, run
#    php artisan config:cache, then the breaker resets on the next success.

# 5. N+1 fix on real data: trigger the report flow (or time the query in tinker)
php artisan tinker --execute="
use App\Models\BotLog;
\$t = microtime(true);
\$r = BotLog::where('webhook_endpoint_uri','webhook-quran-word')
    ->where('created_at','>=', now()->subDay(60))
    ->selectRaw('chat_id, COUNT(*) as cnt, SUM(created_at >= ?) as cnt_month',[now()->subMonth()->toDateTimeString()])
    ->groupBy('chat_id')->get();
echo 'rows='. \$r->count().' in '.round((microtime(true)-\$t)*1000,1).' ms (single aggregate query)';
"
```

### 7.4 Optional (recommended): index for the report query

The new single aggregate still does a full scan on `bot_logs`. If the table is large in
production, add:

```sql
ALTER TABLE bot_logs ADD INDEX idx_bot_logs_webhook_created (webhook_endpoint_uri, created_at);
```

This is safe online on InnoDB (in-place). Add via cPanel → phpMyAdmin or SSH mysql.

---

## Status legend used in the final report

- **FIXED** — code changed, committed, pushed to GitHub.
- **VERIFIED** — verified with real (non-mock) execution on real data/endpoints.
- **OPERATIONAL ACTION REQUIRED** — needs token/provider/owner action; ready-to-run steps above.
- **BLOCKED** — could not be executed from this machine (no SSH route to the host).
