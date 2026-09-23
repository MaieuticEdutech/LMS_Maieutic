# Data retention and deletion procedure

Satisfies NFR-DATA-04 ("a documented data-retention and deletion procedure MUST
exist for student accounts") and records the retention posture of every other
table that holds personal or financial data, so this is a single reference
rather than something scattered across migration comments.

This document describes **what the system does today**, not an aspirational
policy. Where automation doesn't exist yet, that's stated plainly as an open
item rather than implied to be handled — a retention doc that overstates what's
automated is worse than one that's honest about what's still manual.

---

## 1. What personal data this system collects

Per NFR-DATA-01, deliberately narrow: **name, email, phone (optional), avatar
(optional)**. Nothing beyond what a course-selling LMS needs to operate. No
card or bank details are ever collected here — NFR-DATA-02: all payment
instrument capture happens on Razorpay's hosted checkout, so there is no card
number, CVV, or bank account anywhere in this database to retain or delete in
the first place.

## 2. Retention by table

| Data | Retention today | Why |
|---|---|---|
| **User accounts** (`users`) | Indefinite until an admin deletes the account; soft-deleted, not purged | See §3 |
| **Orders / payments** (`orders`, `payments`) | Indefinite, never deleted | Financial records — accounting/tax obligations outlive any individual account, and a refund or dispute months later needs the original record. NFR-DATA-05 makes this explicit: deleting a student must never remove their financial history. |
| **Enrollments** (`enrollments`) | Indefinite | Proof of what was purchased and when; same reasoning as orders. |
| **Certificates** (`certificates`) | Indefinite | A certificate is evidence of a real achievement; its snapshot columns (recipient name, course title at time of issue) are deliberately decoupled from the live `users`/`courses` rows precisely so it survives a rename or a deletion elsewhere. |
| **Audit logs** (`audit_logs`) | Indefinite, and literally cannot be deleted | NFR-SEC-16/17 — append-only by design (model-level guard + a database trigger as of Phase 14, see `2026_09_23_075355_add_append_only_trigger_to_audit_logs_table.php`). `user_id` is set to `NULL` when the actor's account is deleted rather than the row being removed — the action itself is not forgotten, only the link to a specific still-existing account. |
| **Email logs** (`email_logs`) | Indefinite today; no purge job exists | Metadata only (recipient, subject, status) — no message body or token is ever stored (see `EmailLogger`'s own design). Low sensitivity, but see §5. |
| **Webhook events** (`webhook_events`) | Indefinite today; no purge job exists | Raw gateway payloads, which include the buyer's email/phone. See §5. |
| **Media files** (`media_files` + the underlying disk object) | Deleted immediately when its parent (lesson, course, etc.) is deleted, via `MediaStorageService::deleteDirectoryFor()` | Not retained past its owning record. |
| **Sessions** (`sessions` table, database driver) | `SESSION_LIFETIME` minutes of inactivity (120 by default), then expired and swept by Laravel's garbage collector | Standard session lifecycle, not a retention decision specific to this app. |
| **Database backups** | 30 days (NFR-AVAIL-02) | Matches the standard "how far back can we restore" window; not itself a personal-data retention decision, but it does mean a deleted account's data can resurface from a backup restore for up to 30 days — documented here so that's not a surprise during an incident. |
| **Password reset / activation links** | 60 / 4320 minutes respectively (`LMS_PASSWORD_RESET_TTL`, `LMS_ACTIVATION_LINK_TTL`) | Time-boxed by design; expired links are simply invalid, not separately purged (the token lives in the signed URL itself, not a stored row). |
| **Signed media URLs** | 300 seconds max (`LMS_MEDIA_URL_TTL`, hard-capped in `config/lms.php` regardless of what `.env` requests) | NFR-SEC-22. |

## 3. What "deleting a student" actually does today

`App\Actions\Admin\DeleteUser` performs a **soft delete** — `deleted_at` is set,
the row is excluded from normal queries, but every column (name, email, phone)
remains in the database exactly as it was. This is deliberate for the reason
NFR-DATA-05 states: financial and audit history must survive a deletion, and
those records reference the user by ID.

**What this means in practice: a soft-deleted account's personal data is not
actually erased.** It's hidden from the product, not removed from the
database. This satisfies "the student no longer has an account and no longer
appears anywhere in the UI," but it does **not** satisfy a genuine
right-to-erasure request on its own.

### If someone asks for their data to actually be removed

There is no self-service tooling for this yet (NFR-DATA-06, explicitly
[FUTURE] in requirements.md — not part of this MVP). Until that exists, an
erasure request is a manual operator procedure:

1. Confirm the requester's identity through the normal support channel.
2. Soft-delete the account first if it isn't already (`DeleteUser`), which
   immediately removes it from every UI surface.
3. Decide what can actually be scrubbed versus what's legally required to
   keep:
   - **Can be scrubbed**: `name`, `email`, `phone`, `avatar_path` on the
     `users` row — replace with an anonymized placeholder (e.g.
     `deleted-user-{id}@lms.invalid`, name `"Deleted user"`) rather than
     nulling them, since several columns are `NOT NULL`.
   - **Cannot be scrubbed**: `orders`, `payments`, `enrollments` rows tied to
     that user — these are financial records with their own retention
     obligation (§2) that outlives the account itself. What CAN be done is
     confirming they no longer expose personal data beyond what's needed for
     accounting (they don't collect anything beyond name/email/phone to begin
     with, per §1).
   - **Never scrubbed**: `audit_logs` — NFR-SEC-16 exists specifically so an
     audit trail can't be edited to order, including in response to a
     deletion request. The `user_id` link goes to `NULL` (the existing
     `ON DELETE SET NULL` behavior if the row is ever hard-deleted), but the
     `description` field already captured a human-readable summary at write
     time, independent of the live user row.
4. Record that this happened — ironically, in the audit log itself
   (`action: 'user.data_scrubbed'` or similar), since "we received and
   fulfilled an erasure request" is itself something worth being able to
   prove happened.

This is a manual runbook today, not a button. That's an accurate description
of the current state, not a gap being papered over — automating steps 2-4 into
a real admin action is a reasonable future addition once there's actual demand
for it (NFR-DATA-06).

## 4. Why nothing here is ever hard-deleted by default

Every "delete" action a user or admin can trigger in the product today is a
soft delete (users) or leaves the row entirely alone (orders, payments,
enrollments, certificates, audit logs). Hard deletion only happens for content
that has no independent legal/financial weight — media files, course/lesson
content. This asymmetry is intentional: it is much easier to later decide a
soft-deleted row should be purged than to recover data that's already gone,
and the two tables where "we might get sued or audited" applies (orders,
payments) are exactly the ones this system refuses to ever silently drop.

## 5. Open items — not yet automated

Being honest about what NFR-DATA-04 doesn't yet fully cover, so these don't
get silently assumed to be handled:

- **No scheduled purge job for `email_logs` or `webhook_events`.** Both
  accumulate indefinitely. Neither is high-sensitivity (email logs are
  metadata-only; webhook payloads carry a buyer's email/phone but nothing
  more sensitive than what's already in `orders`), but "indefinite" isn't
  really a retention policy — a future pass should add a scheduled command
  purging entries past some age (a year, say) once there's a clear owner
  decision on the exact window.
- **No self-service erasure or export tooling** — explicitly [FUTURE] per
  NFR-DATA-06. §3's manual runbook is the interim procedure.
- **No automated hard-purge of long-soft-deleted accounts.** A soft-deleted
  user's PII sits in the database forever unless someone manually runs the
  scrub procedure in §3. Whether that should ever happen automatically (e.g.
  "scrub PII on any account soft-deleted for over N years with no financial
  activity in the window") is a policy decision for whoever owns compliance,
  not something to decide unilaterally in this document.
