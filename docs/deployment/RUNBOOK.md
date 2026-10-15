# Production runbook — Hostinger VPS + Coolify

Phase 16 deliverable (`phases.md`). Written from the repository and from the Coolify deployment
log of 2026-10-13. **Anything marked ⚠ VERIFY could not be confirmed from the repo** and must be
checked in the Coolify dashboard before this runbook is treated as true.

Secrets never appear here. Variable names only; values live in Coolify's environment tab.

---

## 1. Where Phase 16 stands

| Definition-of-Done item | State | Who |
|---|---|---|
| Production running | 🟢 live (Hostinger VPS, Mumbai, Coolify) | — |
| Staging mirroring production | 🔴 not built | Dev team, Coolify |
| Deployment documented | 🟢 this file | — |
| Rollback documented | 🟢 §7 — **not yet rehearsed** | Dev team |
| Backup **and restore** verified (AC-38) | 🔴 not done | Dev team |
| AC-35 (HTTPS, HSTS, headers) in production | 🟡 headers shipped in Phase 14; verify live, §9 | Dev team |
| Live Razorpay webhook verified | 🔴 blocked on PD-09 credentials | Owner |
| Workers and scheduler confirmed running | 🔴 see §4 — no worker existed on 2026-10-08 | Dev team |
| Production email, SPF/DKIM/DMARC (PD-07) | 🔴 not confirmed | Owner |

Phase 16 stays 🟡 until every row is green (Rule 25).

---

## 2. Resources in Coolify

One project, one environment per tier (`production`; add `staging` as a clone).

| Resource | Purpose | Notes |
|---|---|---|
| **Web app** | Laravel, behind Coolify's Traefik proxy | Built from `MaieuticEdutech/LMS_Maieutic.git:main` |
| **Queue worker** | Sends mail, grades attempts, issues certificates | Same repo and env as the web app. Must exist — mail is queued |
| **Scheduler** | Runs `schedule:run` every minute | Coolify *Scheduled Tasks* on the web app, or a dedicated resource |
| **PostgreSQL 17** | The database | Separate Coolify database resource |
| **Redis** | Queue (and cache/session if set) | Provisioned in production per `planning.md` (2026-10-08). `.env.example` defaults to the `database` driver for local dev. With Redis, `/up` reports `checks.queue: unknown` and Admin → Queue health cannot show pending jobs: confirm the worker from its own logs (§4) |

The repository has **no Dockerfile, compose file or Nixpacks config**; Coolify builds the image
itself. ⚠ VERIFY which build pack the web app uses (Settings → General → Build Pack) and write it here.

---

## 3. Persistent storage — read this before every redeploy

A container's filesystem is discarded on each deploy. Student data lives on disk unless it is
moved to a bucket, and **today most of it is not switchable to a bucket**:

| Disk | Holds | Path | Can move to S3 by config? |
|---|---|---|---|
| `content` | Course videos, PDFs, slides | `storage/app/content` | **Yes** — `LMS_CONTENT_DISK=s3_content` |
| `profile_documents` | Learner photo and Aadhaar scan (ciphertext) | `storage/app/profile-documents` | **Yes, once PR `phase/16-storage-health` is merged** — `LMS_PROFILE_DOCUMENTS_DRIVER=s3` (optional `LMS_PROFILE_DOCUMENTS_BUCKET`); until then hard-wired `local`. Existing files do **not** move by themselves |
| `public` | Course thumbnails, instructor photos | `storage/app/public` | **No** |
| `temp` | In-flight uploads | `storage/app/temp` | No (transient) |

So **mount one persistent volume on the app's `storage/app` directory** and back it up. One mount
covers all four rows. ⚠ VERIFY in Coolify → web app → *Persistent Storage* that it exists. Find the
absolute path inside the container with:

```bash
php artisan tinker --execute='echo storage_path("app");'
```

Mount the **worker** on the same volume, or it cannot read what the web app wrote.

Moving `content` to a bucket (Cloudflare R2 per `HOSTING-COMPARISON.md` §4.3) is still the right
long-term step for video, but it does **not** remove the need for the volume while the
`public` disk (thumbnails, instructor photos) stays local. `profile_documents` can move to a
private bucket with `LMS_PROFILE_DOCUMENTS_DRIVER=s3` once that PR is merged; **copy existing
files across before switching**, or documents already uploaded become unreachable. The `public`
disk is not switchable (it would also need the bucket's origin in the CSP `img-src`) — §10.

---

## 4. Queue worker and scheduler

Commands (run as the container start/`Command` of each resource):

```bash
# Worker — restarts itself hourly so a leak cannot grow forever.
# --queue is REQUIRED: the app uses four named queues, and without it the
# worker drains only `default`, so no email is ever sent and no payment
# webhook is ever processed. Order is priority, left to right.
php artisan queue:work --queue=critical,mail,default,low --tries=3 --timeout=60 --max-time=3600 --sleep=3

# Scheduler — Coolify Scheduled Task, frequency "* * * * *"
php artisan schedule:run
```

The `--tries` value matches `LMS_MAIL_TRIES`; the command matches the README's. The queue names
come from `config/lms.php` (`critical` payments and enrollment, `mail` every email, `default`
progress and audit, `low` cleanup and exports). **A worker is a separate Coolify resource** from
the web app, built from the same repo with the same environment variables. Scheduled commands
registered in `routes/console.php`:
`lms:orders:reconcile`, `lms:orders:cancel-abandoned`, `lms:attempts:expire`,
`lms:enrollments:expire`, `queue:prune-failed`, `queue:prune-batches`, `auth:clear-resets`.

**Confirm both are running** (a Phase 16 test requirement):
- **Worker resource:** it exists in Coolify and shows *Running*. Its logs show `Processing: ...` / `DONE` lines. A worker that only prints "Waiting" while mail is queued is on the wrong queues (check `--queue=`).
- **End to end (the real test):** trigger a password reset to a mailbox you control. The email arrives within a minute.
- **Failed jobs:** Admin → *Queue health* lists them. With the **database** driver it also shows pending jobs and the age of the oldest; with **Redis** (production) it cannot, so rely on the two checks above.
- `php artisan schedule:list` lists the tasks; the next due time advances every minute.

`GET /up` checks the database, the cache and the content disk (a failure returns 503). Once PR
`phase/16-storage-health` is merged it also reports `checks.queue`: `ok`, `stalled` (oldest waiting
job older than 5 minutes) or `unknown` (non-database queue driver). A stalled queue sets
`status` to `degraded` but keeps HTTP 200, so a container healthcheck cannot pull the web app out
of service because the separate worker died — **alert on `status != ok`**. It does not check the
scheduler.

---

## 5. Environment variables (names only)

Set in Coolify on the **web app and the worker**.

| Variable | Production value / note |
|---|---|
| `APP_ENV`, `APP_DEBUG` | `production`, `false` |
| `APP_KEY` | Generate once; **never rotate casually** — encrypted profile documents depend on it |
| `APP_URL` | `https://lms.maieutic.co.in` |
| `SESSION_SECURE_COOKIE` | `true` |
| `DB_*` | Coolify's Postgres internal host/port, own user, strong password |
| `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | `redis` in production (the Redis resource exists); `database` is the local-dev default. Confirm the actual values in Coolify |
| `MAIL_*`, `MAIL_FROM_ADDRESS` | The production provider (PD-07) and a verified sending domain |
| `LMS_CONTENT_DISK` | `content` (volume) or `s3_content` (bucket) |
| `AWS_*` | Only if `s3_content`; for R2 add `AWS_ENDPOINT` and `AWS_USE_PATH_STYLE_ENDPOINT=true` |
| `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `RAZORPAY_WEBHOOK_SECRET` | Live keys — environment only, never committed |
| `SENTRY_LARAVEL_DSN` | Set; confirm with `php artisan sentry:test` |
| `LMS_MAIL_PREVIEW_ENABLED` | `false` |

`APP_KEY` is called out because losing it makes every stored Aadhaar scan unreadable.

---

## 6. Deploy sequence

Set as Coolify's **post-deployment command** on the web app:

```bash
php artisan storage:link --force      # NEW container has no public/storage link
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart             # so the worker loads the new code
```

`storage:link` matters: without it, `public/storage` does not exist in a fresh container, and
every thumbnail and instructor photo served from the `public` disk is a broken image. Nothing in
the repository ran it before this runbook.

Order of a normal release:
1. CI green on the PR (lint, analyse, full test, build assets, dependency audit).
2. Merge to `main`.
3. Redeploy the **web app**, then the **worker** (it builds the emails).
4. Run the smoke test (§8).

Migrations are expand-then-contract: add columns and tables in one release, remove them in a
later one, so the previous release keeps working against the new schema. Never renumber a merged
migration.

---

## 7. Rollback

**Code:** Coolify → web app → *Deployments* → pick the last known-good deployment → *Redeploy*
(⚠ VERIFY the button name). Redeploy the worker to the same deployment.

**Database:** a rollback of code does not undo migrations. Because migrations are additive, the
previous code normally runs fine against the newer schema. If a migration was destructive or
wrong:
1. Put the site in maintenance mode: `php artisan down`.
2. For a reversible migration: `php artisan migrate:rollback --step=1`.
3. Otherwise restore from backup (§8.2) into a scratch database first, check it, then decide.
4. `php artisan up`.

**Rehearse this on staging before relying on it.** Record the date and result in §11.

---

## 8. Backups and restore (AC-38)

### 8.1 Backups
- **Database:** Coolify → Postgres resource → *Backups* → schedule nightly, retention 30 days,
  destination an S3-compatible bucket (R2), **not** the VPS disk. The weekly Hostinger VPS
  snapshot is a safety net, not the plan.
- **Files:** the `storage/app` volume. Back it up to the same bucket (Coolify volume backup, or a
  scheduled task running `rclone`/`aws s3 sync` ⚠ VERIFY which Coolify supports on your version).
  Without this, student documents exist in exactly one place.

### 8.2 Restore rehearsal (do this once, then record it)
1. Create a scratch Postgres database (or the staging one).
2. Download last night's dump from the bucket.
3. `pg_restore --clean --if-exists --no-owner -d <scratch> <dump>`
4. Point a throwaway app instance at it; log in as the super admin; open a course, a student and an
   order.
5. Check row counts against production for `users`, `courses`, `enrollments`, `orders`.
6. Record the wall-clock time from "start" to "app usable" — that is your real recovery time.

A backup that has never been restored is not a backup.

---

## 9. Verification checklist

Run after every release.

**Health**
```bash
curl -s https://lms.maieutic.co.in/up        # {"status":"ok", checks: database, cache, storage}
```

**AC-35 — headers and TLS**
```bash
curl -sI https://lms.maieutic.co.in/ | grep -iE "strict-transport|content-security|x-frame|x-content-type|referrer"
curl -sI http://lms.maieutic.co.in/  | head -3          # must redirect to https
```
Expect `Strict-Transport-Security`, a `Content-Security-Policy` that names `checkout.razorpay.com`,
`cdn.razorpay.com` and `api.razorpay.com`, and `X-Frame-Options: SAMEORIGIN`.

**Smoke test** (PD-09 permitting, in Razorpay *test* mode on staging): register → browse → enrol
(free during the promotion, or test-mode purchase) → activate → open a lesson → take a quiz → see
progress and the certificate.

**Webhook:** Razorpay dashboard → Webhooks → URL `https://lms.maieutic.co.in/webhooks/razorpay` (`POST webhooks/razorpay`, confirmed with `php artisan route:list --path=webhook`); send a test event; confirm it
returns 200 and appears in Admin → *Webhook events*.

**Mail:** send to a Gmail test address; *Show original* must show `SPF: PASS`, `DKIM: PASS`,
`DMARC: PASS`.

---

## 10. Known gaps (not fixed here)

| Gap | Impact | Suggested step |
|---|---|---|
| `public` disk cannot move to S3 | Thumbnails and instructor photos depend on the persistent volume and its backup | Env-driven disk plus the bucket origin in the CSP `img-src` |
| `profile_documents` → bucket needs a one-off copy | Switching the driver strands files already on the volume | Copy `storage/app/profile-documents` into the bucket under the `profile-documents/` prefix, then switch |
| `/up` does not check the scheduler | A stopped scheduler looks healthy | Alert on `lms:` task output, or add a heartbeat the scheduler writes |
| No `storage:link` in deploy (before this runbook) | Broken thumbnails and instructor photos | Add to post-deploy command (§6) |
| Staging does not exist | Rollback and restore cannot be rehearsed safely | Clone the production environment in Coolify |
| PD-07 and PD-09 unresolved | No verified sending domain; no live-payment proof | Owner to supply |

---

## 11. Rehearsal record

| Date | Rehearsal | Environment | Result | By |
|---|---|---|---|---|
| — | Rollback | staging | not yet run | — |
| — | Backup restore (time to usable) | scratch | not yet run | — |
| — | Webhook end to end | production | blocked on PD-09 | — |
