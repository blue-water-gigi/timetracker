# Statistics performance checks

This directory contains the reproducible local load check for the four statistics endpoints.

The test uses the isolated `time_track_load` PostgreSQL database and the `php-load` / `nginx-load` services from `docker-compose.load.yaml`. It must never be pointed at development, test, or production data.

## Data profile

- 1 organization and workspace;
- 10 projects;
- 50 active management members;
- 52 weekly timesheets per member and project;
- 26,000 timesheets with deterministic 80/10/5/5 status distribution;
- 130,000 time entries dated from 2025-08-11 through 2026-08-07.

## Commands

Run commands from the project root. Set a local-only password in the shell before seeding and running k6.

```bash
export LOAD_TEST_PASSWORD='local-load-password'

docker compose -f docker-compose.yaml -f docker-compose.load.yaml run --rm php-load \
    php artisan config:clear

docker compose -f docker-compose.yaml -f docker-compose.load.yaml run --rm php-load \
    php artisan migrate:fresh --force

docker compose -f docker-compose.yaml -f docker-compose.load.yaml run --rm php-load \
    php artisan db:seed --class=StatisticsLoadSeeder --force

docker compose -f docker-compose.yaml -f docker-compose.load.yaml up -d php-load nginx-load
```

Read the generated workspace and project IDs from the seeder output. Then run:

```bash
docker compose -f docker-compose.yaml -f docker-compose.load.yaml run --rm \
    -e PROFILE=smoke -e WORKSPACE_ID=1 -e PROJECT_ID=1 \
    k6 run /scripts/statistics.js
```

Replace the example IDs with the actual values. Supported profiles are `smoke`, `warmup`, `baseline`, `peak`, and `soak`.

## Safety checks

Before `migrate:fresh`, run `php artisan db:show --database=pgsql` through `php-load`. The output must contain `Database time_track_load`. `StatisticsLoadSeeder` also refuses any other database. `postgres-load-prepare` creates the load database idempotently and never resets the shared PostgreSQL volume.

Validate Compose with:

```bash
docker compose -f docker-compose.yaml -f docker-compose.load.yaml config --quiet
```

## Profiles and output

The measured period is `2025-08-13` through `2026-08-13`. Every VU logs in once with its own Sanctum session and keeps its cookies between iterations. One iteration sleeps for one second, so the actual rate is `1 / (response time + 1 second)` rather than exactly one request per second.

```bash
docker compose -f docker-compose.yaml -f docker-compose.load.yaml --profile performance run --rm \
    -e PROFILE=baseline -e WORKSPACE_ID=1 -e PROJECT_ID=1 \
    k6 run --summary-export=/results/baseline-01.json /scripts/statistics.js
```

Profiles: `smoke`, `warmup`, `baseline`, `peak`, and `soak`. JSON summaries and EXPLAIN plans are written to `tests/Performance/results` and ignored by Git.

Capture the real Query-class SQL and generate all 15 PostgreSQL JSON plans with:

```bash
docker compose -f docker-compose.yaml -f docker-compose.load.yaml exec -T php-load \
    php tests/Performance/explain-statistics.php
```

The script is read-only and requires the exact `time_track_load` database name.

## Measured environment

Measured locally on 2026-08-13 in Docker Desktop / WSL2 without explicit container resource limits. This is a production-like local PHP-FPM baseline with Xdebug and display errors disabled, not a production capacity guarantee.

| Component | Version |
|---|---:|
| PHP | 8.4.12 |
| Laravel | 12.63.0 |
| PostgreSQL | 17.10 |
| nginx | 1.30.3 |
| k6 | 2.1.0 |

Final database size is 36 MB. The covering index is 5.0 MB. The replaced non-covering index was 3.9 MB, so net index growth is about 1.1 MB.

## Baseline and index A/B

Each value is the median of three independent five-minute runs. Every check passed; HTTP, access, 429, and 5xx error rates were zero.

| Endpoint | p50 before | p95 before | p50 after | p95 after | p95 change |
|---|---:|---:|---:|---:|---:|
| personal | 74.30 ms | 92.00 ms | 39.67 ms | 57.96 ms | -37.0% |
| project | 90.17 ms | 110.90 ms | 58.83 ms | 75.94 ms | -31.5% |
| team | 59.90 ms | 80.86 ms | 41.86 ms | 61.16 ms | -24.4% |
| workspace | 134.55 ms | 157.93 ms | 134.74 ms | 157.64 ms | -0.2% |

Median throughput increased from 14.83 to 15.20 requests/second, and request count from 4,463 to 4,573. Median overall p99 changed from 164.19 to 160.90 ms.

The candidate was first tested only on `time_track_load`:

```sql
CREATE INDEX candidate_time_entries_statistics_covering
    ON time_entries (timesheet_id, work_date)
    INCLUDE (hours, is_overtime);
```

Without it, personal/project/team SQL repeatedly scanned all 130,000 `time_entries`. With it, PostgreSQL used `Index Only Scan` with zero heap fetches:

| Query group | SQL before | SQL after |
|---|---:|---:|
| personal | 14.2-15.2 ms | 1.0-1.5 ms |
| project, excluding member count | 14.8-18.4 ms | 3.6-7.1 ms |
| team | 17.3-19.3 ms | 3.6-7.1 ms |
| workspace | 22.9-36.6 ms | 21.9-43.9 ms |

Workspace reads roughly 80% of `time_entries`, so its sequential scan remains appropriate. No query produced temp reads/writes or disk sort. The migration replaces the original `(timesheet_id, work_date)` index instead of retaining duplicate B-trees. A clean seed remained about 11 seconds.

## Peak and soak

| Profile | Requests | Rate | personal p95/p99 | project p95/p99 | team p95/p99 | workspace p95/p99 | Errors |
|---|---:|---:|---:|---:|---:|---:|---:|
| peak, up to 46 VU | 9,335 | 23.90/s | 61.55/105.90 ms | 79.25/135.54 ms | 64.50/131.16 ms | 158.63/277.90 ms | 0 |
| soak, 16 VU, 15 min | 13,687 | 15.19/s | 50.33/76.79 ms | 67.38/90.51 ms | 50.66/78.73 ms | 151.26/193.74 ms | 0 |

At peak, PostgreSQL used about 140% CPU and PHP-FPM 78%, with no waiting database connection. During soak, PHP memory stayed at 64.8-65.6 MB, PostgreSQL at 68.5-78.5 MB, Redis at 13.7-14.7 MB, and observed connections at 2-3. There was no upward memory, connection, or latency trend.

## Decision

The PostgreSQL covering index is justified: it improves three endpoint p95 values by 24-37%, reduces the observed PostgreSQL baseline CPU snapshot from about 97% to 66%, adds about 1.1 MB net storage, and did not measurably slow the bulk seed.

Response caching is not needed for the MVP. Baseline, peak, and soak pass with large margins, while correct invalidation after approve/reject/edit/delete would add unnecessary complexity.
