# Software Requirements Specification — LMS

| Field | Value |
|---|---|
| Product | Learning Management System (single organisation) |
| Document | Software Requirements Specification (SRS) |
| Version | 1.1 |
| Status | Revision 1.1 — incorporates the customer Phase 0 decisions of 2026-08-12. Awaiting Phase 0 sign-off. |
| Last updated | 2026-08-12 |
| Related documents | [architecture.md](architecture.md), [phases.md](phases.md), [planning.md](planning.md) |

---

## 1. Document conventions

### 1.1 Requirement identifiers

Every requirement carries a stable ID so that architecture, phases and tests can reference it:

`<CLASS>-<AREA>-<NN>`

- `CLASS` — `FR` (functional), `NFR` (non-functional), `AC` (acceptance criterion)
- `AREA` — `AUTH`, `RBAC`, `CRS`, `CNT`, `ASMT`, `STU`, `INS`, `ADM`, `PAY`, `ENR`, `MAIL`, `PROG`, `FILE`, `SEC`, `RPT`, `SYS`
- Example: `FR-PAY-04`

### 1.2 Priority markers

| Marker | Meaning |
|---|---|
| **[MVP]** | Must exist in Version 1.0. A phase cannot be closed while an MVP requirement it owns is unmet. |
| **[V1.1]** | Planned for the first post-launch increment. Architecture must not block it. |
| **[FUTURE]** | Explicitly deferred. Architecture must not make it prohibitively expensive, but no code is written now. |

### 1.3 Obligation language

`MUST` = mandatory · `SHOULD` = strongly recommended, deviation requires an ADR · `MAY` = optional.

---

## 2. Project overview

The LMS is a web application that lets a single education organisation sell and deliver structured online courses. An administrator builds courses out of modules and lessons, attaches learning material (video, PDF notes, presentations, downloadable resources), and attaches assessments (quizzes at lesson/module level, a final test at course level). Students discover courses publicly, purchase them online through Razorpay, and — **only after the payment gateway has confirmed the payment to the backend** — receive an account and access to the course content. Instructors are assigned to specific courses and manage assessments and monitor the performance of students on those courses only.

The system is deliberately built for **one organisation** in Version 1.0. It is, however, architected so that a future version can introduce multiple organisations without a rewrite (see §22 and `architecture.md` §24).

### 2.1 Problem statement

The organisation currently has no controlled way to (a) sell course access online, (b) guarantee that access is granted only against verified payment, (c) deliver protected learning material, and (d) measure whether students actually progress through and pass the material.

### 2.2 Product positioning

A self-hosted, single-organisation LMS. It is not a marketplace, not a course-authoring SaaS, and not a public multi-vendor platform.

---

## 3. Project goals

| ID | Goal | Measure of success |
|---|---|---|
| G-01 | Sell course access online with verified payment | 100% of enrollments trace to a webhook-verified payment or an explicit, audited admin grant |
| G-02 | Deliver structured, protected learning content | No learning asset is reachable by an unauthenticated or unenrolled request |
| G-03 | Enforce role boundaries | An instructor can never read or write data belonging to a course they are not assigned to |
| G-04 | Measure learning | Lesson, module, course and overall progress are queryable per student at any time |
| G-05 | Assess learning | Quizzes and tests are auto-graded with configurable marks, pass percentage, time limit and attempt limits |
| G-06 | Onboard buyers frictionlessly | A buyer with no prior account can go from payment to logged-in course access using only an emailed one-time link |
| G-07 | Remain extensible | Adding a new content type or a second organisation does not require changing existing domain tables' semantics |

### 3.1 Non-goals for Version 1.0

Being a general-purpose CMS, supporting live classes, supporting SCORM/xAPI packages, and supporting multiple organisations.

---

## 4. Scope

### 4.1 In scope — Version 1.0 (MVP)

1. Public course catalogue and course detail pages (pre-purchase browsing of **metadata only** — title, description, outcomes, requirements, instructors, curriculum titles/durations, price).
2. Razorpay checkout for a single **paid** course per order, with server-side webhook verification.
3. Automatic account creation on first purchase + secure one-time activation/set-password link.
4. Enrollment lifecycle (created only by verified payment or audited admin grant).
5. Course → Module → Lesson content hierarchy.
6. Content types: video, PDF/notes, PPT/PPTX presentations, generic downloadable resources, rich-text lesson, quiz.
7. Course-level final test.
8. Assessment engine: question authoring, marks, negative marking, passing percentage, time limit, attempt limits, auto-grading, result display rules.
9. Progress tracking at lesson, module, course and student-overall level, including "continue where you left off".
10. Three roles: Super Admin, Instructor, Student, with policy-enforced authorisation.
11. Administrator Area with a Course Builder.
12. Instructor Area scoped to assigned courses.
13. Student Area: dashboard, My Courses, player, assessments, results, payment history, profile.
14. Transactional email via queued Laravel Mail.
15. Protected file/video delivery.
16. Basic reports and CSV export.
17. Audit logging of security- and money-relevant actions.

### 4.2 Out of scope — Version 1.0

| Item | Rationale | Revisit |
|---|---|---|
| Multi-organisation / multi-tenancy | Explicit customer decision | V2 — architecture prepared |
| Public REST/GraphQL API for third parties | No consumer identified | [FUTURE] |
| Mobile native applications | Responsive web is sufficient | [FUTURE] |
| Live classes, webinars, video conferencing | Different problem domain | [FUTURE] |
| Certificate generation | Not requested | [V1.1] |
| Discussion forums, Q&A, comments | Not requested | [FUTURE] |
| Coupons, discounts, bundles, subscriptions | Not requested; single-course one-time purchase only | [V1.1] |
| Free courses | **Business decision: all V1 courses are paid.** No zero-amount order path, no free-course claim flow | [V1.1] |
| Free-preview lessons for guests | **Business decision: guests see course metadata only.** No preview flag, no preview branch in the access gate | [V1.1] |
| Multi-currency | INR only via Razorpay | [FUTURE] |
| Multi-language UI (i18n) | English only | [FUTURE] |
| SCORM / xAPI / LTI | Not requested | [FUTURE] |
| DRM-protected video, forensic watermarking | Cost and complexity | [FUTURE] |
| Assignment upload + manual grading | Not requested | [V1.1] |
| Question bank shared across assessments | Simplification for MVP | [V1.1] |
| Instructor self-registration / instructor payouts | Instructors are created by Super Admin | [FUTURE] |
| Refund self-service | Refunds handled manually in the Razorpay dashboard; system records the resulting webhook | [V1.1] |
| Gamification, badges, leaderboards | Not requested | [FUTURE] |
| Push / SMS / WhatsApp notifications | Email only | [FUTURE] |

---

## 5. User roles

### 5.1 Role definitions

| Role | Enum case | Stored value | Created by | Purpose |
|---|---|---|---|---|
| Super Admin | `UserRole::SuperAdmin` | `super_admin` | Seeder (first) / another Super Admin | Full control of the platform |
| Instructor | `UserRole::Instructor` | `instructor` | Super Admin | Assessment authoring and student monitoring for assigned courses only |
| Student | `UserRole::Student` | `student` | Self-registration or automatic creation at purchase | Consume purchased courses |
| Guest | *(unauthenticated)* | — | — | Browse catalogue metadata, view course details, purchase |

### 5.2 Role capability matrix

| Capability | Guest | Student | Instructor | Super Admin |
|---|:--:|:--:|:--:|:--:|
| Browse catalogue / view course detail **metadata** | ✔ | ✔ | ✔ | ✔ |
| Purchase a course | ✔ | ✔ | ✔ | ✔ |
| Access enrolled course content | ✖ | ✔ *(if enrolled)* | ✔ *(if assigned or enrolled)* | ✔ |
| Manage own profile | ✖ | ✔ | ✔ | ✔ |
| Create / edit / delete courses, modules, lessons | ✖ | ✖ | ✖ | ✔ |
| Publish / unpublish courses | ✖ | ✖ | ✖ | ✔ |
| Upload learning content | ✖ | ✖ | ✖ | ✔ |
| Create / edit assessments and questions | ✖ | ✖ | ✔ *(assigned courses)* | ✔ |
| Take assessments | ✖ | ✔ *(if enrolled)* | ✖ | ✖ |
| View student progress and results | ✖ | ✔ *(own only)* | ✔ *(assigned courses)* | ✔ |
| Manage students / instructors | ✖ | ✖ | ✖ | ✔ |
| Assign instructors to courses | ✖ | ✖ | ✖ | ✔ |
| View enrollments and payments | ✖ | ✔ *(own only)* | ✖ | ✔ |
| Grant / revoke enrollment manually | ✖ | ✖ | ✖ | ✔ |
| View reports | ✖ | ✖ | ✔ *(assigned courses)* | ✔ |
| View audit log | ✖ | ✖ | ✖ | ✔ |
| Manage platform settings | ✖ | ✖ | ✖ | ✔ |

> **FR-RBAC-01 [MVP]** — In Version 1.0 a user holds exactly **one** role. The data model MUST store the role in a way that can later become many-to-many without changing call sites (see `architecture.md` §7.2). See Pending Decision **PD-02** in `planning.md`.

---

## 6. Functional requirements — Authentication

> **Foundation (C-06):** authentication is built on **Laravel Fortify** — the headless first-party backend for login, logout, registration, password reset, email verification, password confirmation and login rate limiting. Fortify supplies no views; the LMS supplies its own Blade/Livewire UI and customises Fortify's pipeline for the LMS-specific account states and the purchase-driven activation flow. Authentication MUST NOT be hand-rolled, and no starter-kit UI is adopted.

| ID | Requirement | Priority |
|---|---|---|
| FR-AUTH-00 | Authentication MUST be implemented on Fortify's actions and pipeline, with LMS-owned views registered through Fortify's view callbacks. Fortify features not required in V1 (two-factor authentication, profile-information and password updates via Jetstream-style endpoints) MUST be explicitly disabled rather than left enabled and unused. | [MVP] |
| FR-AUTH-01 | The system MUST allow a student to self-register with name, email and password. | [MVP] |
| FR-AUTH-02 | The system MUST allow any user with a password set and an `active` status to log in with email + password. | [MVP] |
| FR-AUTH-03 | Passwords MUST be hashed with Laravel's default hasher (bcrypt or argon2id). Plaintext passwords MUST NEVER be stored, logged or emailed. | [MVP] |
| FR-AUTH-04 | The system MUST support "forgot password" via a hashed, expiring, single-use emailed token. | [MVP] |
| FR-AUTH-05 | The system MUST support account activation for auto-created accounts using a **one-time set-password link**. A raw password MUST NEVER be emailed. | [MVP] |
| FR-AUTH-06 | Activation and reset links MUST expire (default 72 h activation, 60 min reset — configurable) and MUST be invalidated after first successful use. | [MVP] |
| FR-AUTH-07 | The system MUST support logout and MUST regenerate the session identifier on login and invalidate it on logout. | [MVP] |
| FR-AUTH-08 | Login attempts MUST be rate-limited per email+IP (default 5 attempts / 60 s lockout, escalating). | [MVP] |
| FR-AUTH-09 | Users with status `inactive` or `suspended` MUST be denied login with a non-enumerating message. | [MVP] |
| FR-AUTH-10 | Email addresses MUST be treated case-insensitively and stored normalised (lower-cased, trimmed) so that `A@x.com` and `a@x.com` are one account. | [MVP] |
| FR-AUTH-11 | Self-registered students MUST verify their email address before their account becomes `active`. Purchase-created accounts are considered verified by the activation-link click. | [MVP] |
| FR-AUTH-12 | The system MUST record `last_login_at` and MUST invalidate other sessions on password change. | [MVP] |
| FR-AUTH-13 | Two-factor authentication for Super Admin and Instructor accounts. Fortify provides this feature; it is **disabled** in V1 and enabled in V1.1 by turning the feature on and building the UI. | [V1.1] |
| FR-AUTH-14 | Social / SSO login (Google, Microsoft). | [FUTURE] |

---

## 7. Functional requirements — Authorisation (RBAC)

| ID | Requirement | Priority |
|---|---|---|
| FR-RBAC-02 | Every authorisation decision MUST be made server-side. Hiding a UI element is presentation only and MUST NEVER be the sole control. | [MVP] |
| FR-RBAC-03 | Route-level protection MUST use middleware; record-level protection MUST use Laravel Policies. | [MVP] |
| FR-RBAC-04 | An Instructor MUST be denied read and write access to any course they are not assigned to, including via direct ID manipulation. | [MVP] |
| FR-RBAC-05 | A Student MUST be denied access to any course they are not actively enrolled in. There are **no** exceptions in V1 — no preview content, no partial access. | [MVP] |
| FR-RBAC-06 | A Student MUST only read their own progress, attempts, results, orders and payments. | [MVP] |
| FR-RBAC-07 | The Super Admin role MUST NOT be assignable through any self-service path. | [MVP] |
| FR-RBAC-08 | A user MUST NOT be able to change their own role or status. | [MVP] |
| FR-RBAC-09 | The last remaining active Super Admin MUST NOT be deletable, deactivatable or demotable. | [MVP] |
| FR-RBAC-10 | Every authorisation failure MUST return 403 (or 404 where existence itself is sensitive) and MUST NOT leak resource details. | [MVP] |
| FR-RBAC-11 | Granular permission sets (custom roles) beyond the three fixed roles. | [FUTURE] |

---

## 8. Functional requirements — Courses

| ID | Requirement | Priority |
|---|---|---|
| FR-CRS-01 | A Super Admin MUST be able to create, read, update and delete courses. | [MVP] |
| FR-CRS-02 | A course MUST have: title, unique slug, short subtitle, rich description, thumbnail, price, currency, level, language, status. | [MVP] |
| FR-CRS-03 | A course MUST have a status of `draft`, `published` or `archived`. Only `published` courses appear in the public catalogue. | [MVP] |
| FR-CRS-04 | Publishing MUST be blocked unless the course passes validation: has a thumbnail, a description, a price greater than zero, and at least one published module containing at least one published lesson. | [MVP] |
| FR-CRS-05 | Unpublishing a course MUST NOT revoke access for already-enrolled students. | [MVP] |
| FR-CRS-06 | Course deletion MUST be a soft delete. A course with any enrollment MUST NOT be hard-deletable through the UI. | [MVP] |
| FR-CRS-07 | Courses MUST support optional categorisation for catalogue browsing and filtering. | [MVP] |
| FR-CRS-08 | A course MUST support structured "what you will learn" outcomes and "requirements" lists. | [MVP] |
| FR-CRS-09 | Prices MUST be stored as integers in the currency's minor unit (paise) to avoid floating-point error. | [MVP] |
| FR-CRS-10 | **All V1 courses are paid.** Every course MUST have a price greater than zero. Free courses are not supported in V1 — there is no zero-amount order path and no free-course claim flow. | [MVP] |
| FR-CRS-10a | Free courses (price zero, enrollment without a gateway round-trip). | [V1.1] |
| FR-CRS-11 | Changing a course price MUST NOT affect the recorded amount of existing orders. | [MVP] |
| FR-CRS-12 | Course drip scheduling (release lessons on a schedule). | [FUTURE] |
| FR-CRS-13 | Course prerequisites (course A required before B). | [FUTURE] |

---

## 9. Functional requirements — Modules & Lessons

| ID | Requirement | Priority |
|---|---|---|
| FR-CNT-01 | A course MUST contain zero or more modules; a module MUST belong to exactly one course. | [MVP] |
| FR-CNT-02 | A module MUST contain zero or more lessons; a lesson MUST belong to exactly one module. | [MVP] |
| FR-CNT-03 | Modules and lessons MUST be explicitly ordered and MUST be reorderable by drag-and-drop in the Course Builder. | [MVP] |
| FR-CNT-04 | Reordering MUST be transactional — a failed reorder MUST leave the original order intact with no duplicate positions. | [MVP] |
| FR-CNT-05 | Modules and lessons MUST be independently publishable; an unpublished item MUST be invisible to students. | [MVP] |
| FR-CNT-06 | A lesson MUST declare a content type from a registry: `video`, `document`, `presentation`, `text`, `resource`, `quiz`. | [MVP] |
| FR-CNT-07 | Adding a new content type MUST require only registering a new handler + view, with **no schema change** to `lessons`. | [MVP] |
| FR-CNT-08 | A lesson MUST support zero or more attached files (primary asset + supplementary downloadables). | [MVP] |
| FR-CNT-09 | Free-preview lessons viewable by guests. **Not in V1** — the `lessons` table carries no preview flag and the access gate has no preview branch (see FR-RBAC-05). Adding it later is an additive migration plus one branch in the access gate. | [V1.1] |
| FR-CNT-10 | Deleting a module MUST require explicit confirmation and MUST cascade to its lessons and their assets, with the action audit-logged. | [MVP] |
| FR-CNT-11 | The Course Builder MUST show, per course, a live outline with counts and total duration. | [MVP] |
| FR-CNT-12 | Lesson content MUST be editable without breaking existing progress records for that lesson. | [MVP] |
| FR-CNT-13 | Content versioning / revision history. | [FUTURE] |
| FR-CNT-14 | Bulk import of course structure (CSV/ZIP). | [FUTURE] |

---

## 10. Functional requirements — Files, video & resources

| ID | Requirement | Priority |
|---|---|---|
| FR-FILE-01 | The Super Admin MUST be able to upload video files to a lesson. | [MVP] |
| FR-FILE-02 | The Super Admin MUST be able to upload PDF notes, PPT/PPTX presentations and generic downloadable resources. | [MVP] |
| FR-FILE-03 | All learning assets MUST be stored on a **private** storage disk, never under the web-server document root. | [MVP] |
| FR-FILE-04 | Upload validation MUST check: authenticated + authorised uploader, file size ceiling per purpose, extension allow-list, MIME allow-list, and server-side content sniffing. Client-reported MIME MUST NOT be trusted. | [MVP] |
| FR-FILE-05 | Stored filenames MUST be system-generated (UUID/ULID-based). The original filename MUST be retained as metadata only. | [MVP] |
| FR-FILE-06 | Every asset request MUST pass an authorisation check that verifies active enrollment (or admin/assigned-instructor rights) for the owning course. | [MVP] |
| FR-FILE-07 | Video playback MUST be served through short-lived, non-guessable, authorised URLs — never a permanent public path. | [MVP] |
| FR-FILE-08 | Video delivery MUST support HTTP Range requests so that seeking works. | [MVP] |
| FR-FILE-09 | Downloadable resources MUST be served with `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, and a non-executable content type. | [MVP] |
| FR-FILE-10 | The storage layer MUST be driver-agnostic: local disk in development, S3-compatible object storage in production, switchable by configuration alone with no code change. | [MVP] |
| FR-FILE-11 | Storage paths MUST be produced by a single path-resolver service so a tenant prefix can be injected later. | [MVP] |
| FR-FILE-12 | Deleting a lesson/course MUST schedule the removal of its orphaned stored objects via a background job. | [MVP] |
| FR-FILE-13 | Upload MUST report progress and MUST support large files without exhausting PHP memory (chunked or direct-to-storage). | [MVP] |
| FR-FILE-14 | PPT/PPTX MUST be downloadable in MVP; in-browser preview is not required. | [MVP] |
| FR-FILE-15 | Video transcoding to adaptive bitrate (HLS/DASH) and thumbnail sprite generation. | [FUTURE] |
| FR-FILE-16 | Subtitles / closed captions (WebVTT). | [V1.1] |
| FR-FILE-17 | Anti-sharing measures: session-bound playback tokens, concurrent-stream limits, visible user watermark. | [FUTURE] |

> **Explicit limitation (must be communicated to the business):** without DRM, a determined, technically capable student can capture video. MVP protection raises the cost of casual sharing; it does not make copying impossible. See `architecture.md` §16.4.

---

## 11. Functional requirements — Assessments (quizzes & tests)

A **quiz** and a **test** are the same structure with different attachment points and intent. Both are modelled by one `Assessment` entity (see `architecture.md` §6.4).

| ID | Requirement | Priority |
|---|---|---|
| FR-ASMT-01 | A quiz MUST be attachable to a lesson or a module. A test MUST be attachable to a course as its final test. | [MVP] |
| FR-ASMT-02 | Super Admin MUST be able to create/edit/delete assessments on any course. Instructors MUST be able to do so **only** on assigned courses. | [MVP] |
| FR-ASMT-03 | An assessment MUST support: title, instructions, passing percentage, optional time limit (minutes), optional max attempts, question shuffle, option shuffle, answer-reveal policy, publish state. | [MVP] |
| FR-ASMT-04 | Question types in MVP MUST include: single choice, multiple choice, true/false, short answer (exact/normalised match). | [MVP] |
| FR-ASMT-05 | Each question MUST carry configurable marks and optional negative marks. | [MVP] |
| FR-ASMT-06 | Total marks MUST be derived from the sum of question marks and MUST NOT be independently editable. | [MVP] |
| FR-ASMT-07 | A single-choice/true-false question MUST have exactly one correct option; a multiple-choice question MUST have at least one. Validation MUST block saving otherwise. | [MVP] |
| FR-ASMT-08 | An assessment MUST NOT be publishable with zero questions or with total marks of zero. | [MVP] |
| FR-ASMT-09 | Students MUST be able to start an attempt only if enrolled, the assessment is published, and their attempt count is below the limit. | [MVP] |
| FR-ASMT-10 | When a time limit is set, the server MUST record the attempt deadline at start time and MUST reject or auto-submit answers received after it. Client-side timers are advisory only. | [MVP] |
| FR-ASMT-11 | Attempts MUST auto-save answers as the student progresses so a browser crash does not lose work. | [MVP] |
| FR-ASMT-12 | Grading MUST be performed server-side on submission. Correct answers MUST NOT be sent to the browser before submission. | [MVP] |
| FR-ASMT-13 | The result MUST record: score in marks, max marks, percentage, pass/fail against the passing percentage, time spent, submitted timestamp. | [MVP] |
| FR-ASMT-14 | Answer reveal MUST honour the configured policy: `never`, `after_submit`, `after_pass`. | [MVP] |
| FR-ASMT-15 | Where multiple attempts are allowed, the system MUST retain every attempt and MUST define the "official" score as the **highest** attempt (configurable per assessment). | [MVP] |
| FR-ASMT-16 | A student MUST NOT have two `in_progress` attempts on the same assessment simultaneously. | [MVP] |
| FR-ASMT-17 | Instructors and Super Admin MUST be able to view attempt lists, per-question breakdowns and cohort statistics for their permitted courses. | [MVP] |
| FR-ASMT-18 | Question ordering (and shuffled order, when enabled) MUST be snapshotted per attempt so a review shows what the student actually saw. | [MVP] |
| FR-ASMT-19 | The final test MAY require completion of all course lessons before it can be started (configurable per course). | [MVP] |
| FR-ASMT-20 | Manually-graded question types (essay, file upload). | [V1.1] |
| FR-ASMT-21 | Reusable question bank shared across assessments, with random selection of N questions. | [V1.1] |
| FR-ASMT-22 | Proctoring, tab-switch detection, IP locking. | [FUTURE] |

---

## 12. Functional requirements — Enrollment

| ID | Requirement | Priority |
|---|---|---|
| FR-ENR-01 | An enrollment MUST be the single source of truth for a student's access to a course. | [MVP] |
| FR-ENR-02 | An enrollment MUST be created only by (a) a payment verified through the gateway webhook or its reconciliation equivalent, or (b) an explicit, audited Super Admin grant. There is no third source in V1. | [MVP] |
| FR-ENR-03 | A browser-side payment "success" callback MUST NEVER by itself create an enrollment or grant access. | [MVP] |
| FR-ENR-04 | A student MUST NOT hold more than one active enrollment for the same course (unique constraint on `user_id` + `course_id`). | [MVP] |
| FR-ENR-05 | Enrollment creation MUST be idempotent — repeated webhook delivery for the same payment MUST NOT create duplicates. | [MVP] |
| FR-ENR-06 | Enrollment MUST record its source (`purchase`, `admin_grant`, `import`), the granting user where applicable, and the originating order where applicable. | [MVP] |
| FR-ENR-07 | Enrollment status MUST be one of `active`, `suspended`, `completed`, `expired`, `refunded`. Only `active` and `completed` grant content access. | [MVP] |
| FR-ENR-08 | Super Admin MUST be able to suspend, reinstate and revoke an enrollment, with the action audit-logged and a reason recorded. | [MVP] |
| FR-ENR-09 | A refund webhook MUST move the enrollment to `refunded` and revoke access. | [MVP] |
| FR-ENR-10 | Enrollment MAY carry an optional expiry date; expired enrollments MUST lose access automatically. | [MVP] |
| FR-ENR-11 | The system MUST show Super Admin a filterable enrollment list (course, student, status, source, date range) with CSV export. | [MVP] |
| FR-ENR-12 | Bulk enrollment by CSV upload. | [V1.1] |
| FR-ENR-13 | Group/organisation seat licences. | [FUTURE] |

---

## 13. Functional requirements — Payments

| ID | Requirement | Priority |
|---|---|---|
| FR-PAY-01 | The system MUST integrate Razorpay Checkout for course purchase in INR. | [MVP] |
| FR-PAY-02 | Purchase MUST be possible by a guest (no prior account) and by a logged-in student. | [MVP] |
| FR-PAY-03 | Every purchase attempt MUST create a server-side `Order` record **before** the gateway is invoked, holding the authoritative amount taken from the database — never from the client. | [MVP] |
| FR-PAY-04 | The gateway order MUST be created server-side via the Razorpay API using the server-side amount. | [MVP] |
| FR-PAY-05 | The system MUST expose a webhook endpoint that verifies the Razorpay signature (HMAC-SHA256 over the **raw** request body using the webhook secret) before any processing. | [MVP] |
| FR-PAY-06 | Requests with an invalid or missing signature MUST be rejected with 400 and logged as a security event. | [MVP] |
| FR-PAY-07 | Webhook processing MUST be idempotent, keyed on the gateway event identifier; a duplicate event MUST be acknowledged and ignored. | [MVP] |
| FR-PAY-08 | The webhook endpoint MUST be CSRF-exempt, rate-limited, and MUST respond `2xx` quickly, deferring business processing to a queued job. | [MVP] |
| FR-PAY-09 | The system MUST handle at minimum: `payment.captured`, `payment.failed`, `order.paid`, `refund.processed`. | [MVP] |
| FR-PAY-10 | The browser return/callback MUST only display status and MUST poll or subscribe for the server-confirmed state. | [MVP] |
| FR-PAY-11 | If the webhook has not arrived by the time the buyer returns, the UI MUST show "payment received — activating your access" and MUST resolve automatically once processed. | [MVP] |
| FR-PAY-12 | A reconciliation job MUST run on a schedule to fetch and settle any `pending` order whose webhook was missed, by querying the gateway API. | [MVP] |
| FR-PAY-13 | The amount and currency confirmed by the gateway MUST be validated against the order before enrollment is granted; a mismatch MUST block enrollment and raise an alert. | [MVP] |
| FR-PAY-14 | Payment records MUST store gateway order id, payment id, method, status, timestamps and the raw gateway payload. | [MVP] |
| FR-PAY-15 | Gateway API keys and webhook secrets MUST come from environment variables only and MUST NEVER be committed or logged. | [MVP] |
| FR-PAY-16 | Students MUST see their own payment history with order number, course, amount, status and date; Super Admin MUST see all payments with filters and CSV export. | [MVP] |
| FR-PAY-17 | Invoices/receipts (PDF, GST fields). | [V1.1] |
| FR-PAY-18 | Additional gateways (Stripe, PayPal), multi-currency, subscriptions, EMI. | [FUTURE] |
| FR-PAY-19 | Coupons and promotional pricing. | [V1.1] |

---

## 14. Functional requirements — Account & email flow at purchase

This is the flow the customer identified as critical. It is specified here as a normative sequence.

**FR-MAIL-01 [MVP] — Verified-payment onboarding.** On receipt of a signature-verified, amount-matched successful payment webhook, the system MUST, inside a single database transaction:

1. Load the `Order` by gateway order id and lock it for update.
2. If the order is already `paid`, stop (idempotency).
3. Resolve the buyer: look up a user by the normalised `buyer_email`.
   - **If no user exists:** create a `student` user with status `pending_activation`, no password set.
   - **If a user exists:** reuse it. A duplicate account MUST NOT be created.
4. Create the `Payment` record and mark the `Order` as `paid`.
5. Create the `Enrollment` (source `purchase`, status `active`, linked to the order) if one does not already exist.
6. Write audit-log entries for the payment and the enrollment.
7. Commit.

After commit (never before), the system MUST queue the appropriate email:

| Buyer state | Email |
|---|---|
| New account created | **Welcome & activate** — course name + a one-time, expiring set-password link |
| Existing account, password already set | **Purchase confirmation** — course name + direct link to My Courses |
| Existing account, still `pending_activation` | **Purchase confirmation + fresh activation link** |

| ID | Requirement | Priority |
|---|---|---|
| FR-MAIL-02 | A raw or generated password MUST NEVER be emailed. Only a one-time activation link is acceptable. | [MVP] |
| FR-MAIL-03 | The activation link MUST be single-use, MUST expire, MUST be tied to the specific user, and MUST be stored hashed at rest. | [MVP] |
| FR-MAIL-04 | Setting the password via activation MUST mark the account `active`, mark the email verified, log the user in, and redirect to the purchased course. | [MVP] |
| FR-MAIL-05 | The student MUST be able to request a new activation link if theirs expired, subject to rate limiting. | [MVP] |
| FR-MAIL-06 | All emails MUST be dispatched through the queue; a failed send MUST retry with backoff and MUST NOT break the enrollment transaction. | [MVP] |
| FR-MAIL-07 | The MVP transactional email set MUST include: email verification, welcome & activate, purchase confirmation, payment failed, password reset, password changed, enrollment granted by admin, enrollment revoked, assessment result summary, course completed. | [MVP] |
| FR-MAIL-08 | Emails MUST use a shared branded layout with the organisation's name, logo and support address drawn from settings, not hardcoded. | [MVP] |
| FR-MAIL-09 | In development, email MUST default to a non-delivering driver (log / Mailpit) so that no real email is ever sent from a developer machine. | [MVP] |
| FR-MAIL-10 | Email sends MUST be recorded (recipient, type, status, timestamp) for support and audit purposes. | [MVP] |
| FR-MAIL-11 | In-app notification centre. | [V1.1] |
| FR-MAIL-12 | Digest emails, marketing emails, unsubscribe preference centre. | [FUTURE] |

---

## 15. Functional requirements — Progress tracking

| ID | Requirement | Priority |
|---|---|---|
| FR-PROG-01 | The system MUST track per-lesson progress: `not_started`, `in_progress`, `completed`. | [MVP] |
| FR-PROG-02 | For video lessons the system MUST persist the last playback position and the furthest watched position, throttled to at most one write every ~15 seconds per lesson. | [MVP] |
| FR-PROG-03 | A video lesson SHOULD auto-complete when the watched fraction crosses a configurable threshold (default 90%). | [MVP] |
| FR-PROG-04 | Non-video lessons MUST support explicit "Mark as complete". | [MVP] |
| FR-PROG-05 | A quiz lesson MUST be considered complete when the student submits a passing attempt (or any attempt, if the quiz is configured as non-blocking). | [MVP] |
| FR-PROG-06 | Module progress MUST be derived as completed-lessons ÷ published-lessons in that module. | [MVP] |
| FR-PROG-07 | Course progress percentage MUST be derived from published lessons and MUST be cached on the enrollment for fast dashboard rendering. | [MVP] |
| FR-PROG-08 | The cached course progress MUST be recalculated whenever a lesson-progress row changes or the course's published lesson set changes. | [MVP] |
| FR-PROG-09 | Adding or removing a lesson from a published course MUST trigger recalculation of every affected enrollment's progress. | [MVP] |
| FR-PROG-10 | The system MUST record the last accessed lesson and timestamp per enrollment so "Continue learning" resumes exactly there. | [MVP] |
| FR-PROG-11 | Course completion MUST be recorded with a timestamp when progress reaches 100% and (if the course requires it) the final test is passed. | [MVP] |
| FR-PROG-12 | Student overall progress MUST aggregate across all their enrollments (courses enrolled, in progress, completed, average score). | [MVP] |
| FR-PROG-13 | Progress data MUST be visible to: the student (own), assigned instructors, and Super Admin. | [MVP] |
| FR-PROG-14 | Progress writes MUST be safe under concurrency (idempotent upsert; a completed lesson MUST NOT revert to in-progress). | [MVP] |
| FR-PROG-15 | Time-on-task analytics and engagement heatmaps. | [FUTURE] |
| FR-PROG-16 | Completion certificates on course completion. | [V1.1] |

---

## 16. Functional requirements — Student

| ID | Requirement | Priority |
|---|---|---|
| FR-STU-01 | A student MUST be able to register, verify email, log in, log out and reset their password. | [MVP] |
| FR-STU-02 | A student dashboard MUST show: courses in progress with % complete, a "Continue learning" entry point, recently accessed courses, recent results, and completed courses. | [MVP] |
| FR-STU-03 | A student MUST be able to browse the published catalogue with search, category filter and sort. | [MVP] |
| FR-STU-04 | A course detail page MUST show description, outcomes, requirements, instructor(s), curriculum outline (module and lesson **titles and durations only**) and price — **without** exposing any protected content. No lesson body, media, resource or assessment is reachable from this page. | [MVP] |
| FR-STU-05 | A student MUST be able to purchase a course and MUST be prevented from purchasing a course they are already enrolled in. | [MVP] |
| FR-STU-06 | "My Courses" MUST list only actively enrolled courses with progress. | [MVP] |
| FR-STU-07 | The course player MUST present the curriculum sidebar, the current lesson content, completion controls and next/previous navigation. | [MVP] |
| FR-STU-08 | A student MUST be able to watch videos with seek, volume, playback rate and resume-from-last-position. | [MVP] |
| FR-STU-09 | A student MUST be able to read/download PDFs, download presentations and download resources for enrolled courses. | [MVP] |
| FR-STU-10 | A student MUST be able to take quizzes and the final test, subject to attempt and time rules. | [MVP] |
| FR-STU-11 | A student MUST be able to view their scores, pass/fail state and — where policy allows — an answer review. | [MVP] |
| FR-STU-12 | A student MUST be able to view their own progress per course and overall. | [MVP] |
| FR-STU-13 | A student MUST be able to view their payment/order history. | [MVP] |
| FR-STU-14 | A student MUST be able to manage their profile: name, phone, avatar, password. Changing the login email MUST require re-verification. | [MVP] |
| FR-STU-15 | A deactivated student MUST lose the ability to log in while retaining their records. | [MVP] |
| FR-STU-16 | Wishlists, ratings, reviews, notes/bookmarks within lessons. | [FUTURE] |

---

## 17. Functional requirements — Instructor

| ID | Requirement | Priority |
|---|---|---|
| FR-INS-01 | An instructor account MUST be created only by a Super Admin. | [MVP] |
| FR-INS-02 | An instructor dashboard MUST show only assigned courses, with enrolled-student counts, average progress and recent assessment activity. | [MVP] |
| FR-INS-03 | An instructor MUST be able to list students enrolled in assigned courses, with per-student progress. | [MVP] |
| FR-INS-04 | An instructor MUST be able to create, edit, publish and delete quizzes and tests **on assigned courses only**. | [MVP] |
| FR-INS-05 | An instructor MUST be able to add, edit, reorder and delete questions and options, and set marks, negative marks, passing percentage, time limit and attempt limits. | [MVP] |
| FR-INS-06 | An instructor MUST be able to view assessment results: per attempt, per student, and aggregate (average score, pass rate, per-question difficulty). | [MVP] |
| FR-INS-07 | An instructor MUST be able to drill into an individual student's progress within an assigned course. | [MVP] |
| FR-INS-08 | An instructor MUST NOT be able to create/edit/delete courses, modules, lessons or learning content in MVP. | [MVP] |
| FR-INS-09 | An instructor MUST NOT be able to see students, enrollments, payments or reports outside assigned courses. | [MVP] |
| FR-INS-10 | An instructor MUST NOT see any payment amounts or financial data. | [MVP] |
| FR-INS-11 | Assigning or unassigning an instructor MUST take effect immediately and MUST be audit-logged. | [MVP] |
| FR-INS-12 | Unassigning an instructor MUST NOT delete assessments they authored. | [MVP] |
| FR-INS-13 | Instructor-authored lessons and content uploads. | [V1.1] |
| FR-INS-14 | Instructor messaging to enrolled students. | [FUTURE] |

---

## 18. Functional requirements — Super Admin / Administrator Area

| ID | Requirement | Priority |
|---|---|---|
| FR-ADM-01 | The Administrator Area MUST live under a distinct route prefix guarded by authentication + role middleware. | [MVP] |
| FR-ADM-02 | The admin dashboard MUST show KPIs: total students, active enrollments, published courses, revenue for a selected period, recent orders, recent enrollments, and failed payments/webhooks needing attention. | [MVP] |
| FR-ADM-03 | Full CRUD + publish/unpublish for courses. | [MVP] |
| FR-ADM-04 | Full CRUD + reorder for modules and lessons via the Course Builder. | [MVP] |
| FR-ADM-05 | Upload/replace/delete of videos, notes, presentations and resources. | [MVP] |
| FR-ADM-06 | Full CRUD for assessments and questions on any course. | [MVP] |
| FR-ADM-07 | Student management: list (search/filter/paginate), create, edit, activate/deactivate, soft-delete, resend activation, force password reset, view detail (enrollments, progress, attempts, orders). | [MVP] |
| FR-ADM-08 | Instructor management: list, create, edit, activate/deactivate, soft-delete, assign/unassign courses. | [MVP] |
| FR-ADM-09 | Manual enrollment grant and revoke, with mandatory reason, audit-logged. | [MVP] |
| FR-ADM-10 | Enrollment listing with filters and CSV export. | [MVP] |
| FR-ADM-11 | Payment/order listing with filters, detail view including raw gateway payload, and CSV export. | [MVP] |
| FR-ADM-12 | A webhook event log with status, and the ability to safely re-process a failed event. | [MVP] |
| FR-ADM-13 | Student progress views: per course, per student, per cohort. | [MVP] |
| FR-ADM-14 | Reports (§19). | [MVP] |
| FR-ADM-15 | Read-only audit log viewer with filters (actor, action, entity, date). | [MVP] |
| FR-ADM-16 | Platform settings: organisation name, logo, support email, currency, default pass percentage, video completion threshold, activation link TTL. Stored in the database, not in code. | [MVP] |
| FR-ADM-17 | Destructive actions (delete course/module/lesson, revoke enrollment, delete user) MUST require typed confirmation and MUST be audit-logged. | [MVP] |
| FR-ADM-18 | Impersonate-student (view-as) for support, heavily audit-logged. | [V1.1] |
| FR-ADM-19 | Every admin list MUST be server-side paginated, searchable and sortable. | [MVP] |

---

## 19. Functional requirements — Reporting

| ID | Requirement | Priority |
|---|---|---|
| FR-RPT-01 | Enrollment report: enrollments per course, per period, by source. | [MVP] |
| FR-RPT-02 | Revenue report: gross revenue per course and per period, count of successful vs failed orders. | [MVP] |
| FR-RPT-03 | Progress report: per course — enrolled, started, in progress, completed, average % complete. | [MVP] |
| FR-RPT-04 | Assessment report: per assessment — attempts, average score, pass rate, per-question correct rate. | [MVP] |
| FR-RPT-05 | Student report: per student — enrollments, progress, attempts, scores, last activity. | [MVP] |
| FR-RPT-06 | Every report MUST support a date-range filter and CSV export. | [MVP] |
| FR-RPT-07 | Reports MUST respect role scope — instructors see only assigned-course data and no financial data. | [MVP] |
| FR-RPT-08 | Large exports MUST run as queued jobs and be delivered by download link rather than blocking the request. | [MVP] |
| FR-RPT-09 | Scheduled emailed reports, charts/BI dashboards. | [FUTURE] |

---

## 20. Non-functional requirements

### 20.1 Performance

| ID | Requirement | Priority |
|---|---|---|
| NFR-PERF-01 | Server-rendered pages MUST respond in < 400 ms at p95 under normal load (excluding media streaming). | [MVP] |
| NFR-PERF-02 | Any list view MUST be paginated; unbounded queries are prohibited. | [MVP] |
| NFR-PERF-03 | N+1 queries MUST be prevented; `Model::preventLazyLoading()` MUST be enabled in non-production environments. | [MVP] |
| NFR-PERF-04 | Dashboard progress figures MUST read cached aggregates, not recompute across all lesson rows. | [MVP] |
| NFR-PERF-05 | The system MUST support at least 200 concurrent active learners and 5,000 total students on modest hardware. | [MVP] |
| NFR-PERF-06 | Any operation exceeding ~2 s (email, transcode, export, recalculation, webhook processing) MUST be queued. | [MVP] |

### 20.2 Scalability

| ID | Requirement | Priority |
|---|---|---|
| NFR-SCAL-01 | The application MUST be stateless at the web tier (sessions, cache and queue in shared stores) so it can run behind more than one app node. | [MVP] |
| NFR-SCAL-02 | Media delivery MUST be offloadable to object storage/CDN without application changes. | [MVP] |
| NFR-SCAL-03 | Queue workers MUST be horizontally scalable and jobs MUST be idempotent. | [MVP] |

### 20.3 Availability & reliability

| ID | Requirement | Priority |
|---|---|---|
| NFR-AVAIL-01 | Target availability 99.5% monthly for the production application. | [MVP] |
| NFR-AVAIL-02 | Nightly automated database backups with a documented, **tested** restore procedure; 30-day retention. | [MVP] |
| NFR-AVAIL-03 | Object storage MUST have versioning or equivalent protection against accidental deletion in production. | [MVP] |
| NFR-AVAIL-04 | Failed queue jobs MUST be recorded, retryable and alerted on. | [MVP] |
| NFR-AVAIL-05 | A gateway outage MUST degrade gracefully — orders remain `pending` and are settled by reconciliation, never lost. | [MVP] |

### 20.4 Maintainability

| ID | Requirement | Priority |
|---|---|---|
| NFR-MAINT-01 | The codebase MUST follow PSR-12 and Laravel conventions; formatting MUST be enforced by Laravel Pint in CI. | [MVP] |
| NFR-MAINT-02 | Static analysis (Larastan) MUST pass at the agreed level in CI. | [MVP] |
| NFR-MAINT-03 | Business logic MUST live in Actions/Services, not in controllers or Livewire components. | [MVP] |
| NFR-MAINT-04 | Every schema change MUST be a migration; no manual production schema edits. | [MVP] |
| NFR-MAINT-05 | Public methods of domain services MUST carry docblocks stating intent, invariants and thrown exceptions. | [MVP] |
| NFR-MAINT-06 | The four planning documents MUST be updated in the same change that invalidates them. | [MVP] |

### 20.5 Usability & accessibility

| ID | Requirement | Priority |
|---|---|---|
| NFR-UX-01 | All interfaces MUST be responsive from 360 px to 1920 px. | [MVP] |
| NFR-UX-02 | The student player MUST be fully usable on a mobile device. | [MVP] |
| NFR-UX-03 | The UI SHOULD meet WCAG 2.1 AA for contrast, focus visibility, keyboard operability and form labelling. | [MVP] |
| NFR-UX-04 | Every destructive action MUST be confirmed; every mutating action MUST give explicit success/failure feedback. | [MVP] |
| NFR-UX-05 | Validation errors MUST be shown inline against the offending field. | [MVP] |
| NFR-UX-06 | Long operations MUST show progress or a queued-work notice, never an unexplained wait. | [MVP] |

### 20.6 Compatibility

| ID | Requirement | Priority |
|---|---|---|
| NFR-COMP-01 | Latest two stable versions of Chrome, Edge, Firefox and Safari (desktop + mobile). | [MVP] |
| NFR-COMP-02 | Video playback MUST work with MP4/H.264 + AAC in all supported browsers. | [MVP] |
| NFR-COMP-03 | Internet Explorer is not supported. | [MVP] |

### 20.7 Data & privacy

| ID | Requirement | Priority |
|---|---|---|
| NFR-DATA-01 | Personal data collected MUST be limited to what the LMS needs: name, email, phone (optional), avatar (optional). | [MVP] |
| NFR-DATA-02 | Card/bank details MUST NEVER touch the application; all payment instrument capture happens on Razorpay's hosted checkout. | [MVP] |
| NFR-DATA-03 | Personal data and secrets MUST NOT appear in application logs. | [MVP] |
| NFR-DATA-04 | A documented data-retention and deletion procedure MUST exist for student accounts. | [MVP] |
| NFR-DATA-05 | Deleting a student MUST be a soft delete preserving financial records for accounting integrity. | [MVP] |
| NFR-DATA-06 | Self-service data export / right-to-erasure tooling. | [FUTURE] |

---

## 21. Security requirements

| ID | Requirement | Priority |
|---|---|---|
| NFR-SEC-01 | All traffic MUST be HTTPS in production, with HSTS enabled. | [MVP] |
| NFR-SEC-02 | All state-changing browser requests MUST carry a valid CSRF token. The gateway webhook is the only exempt route and is protected by signature verification instead. | [MVP] |
| NFR-SEC-03 | All input MUST be validated server-side via Form Requests / Livewire rules before reaching domain logic. | [MVP] |
| NFR-SEC-04 | All database access MUST use Eloquent or parameter-bound queries. String-interpolated SQL is prohibited. | [MVP] |
| NFR-SEC-05 | All output MUST be escaped by default; `{!! !!}` requires sanitisation of the value and a code-review justification. | [MVP] |
| NFR-SEC-06 | Rich-text (course/lesson descriptions) MUST be sanitised against an allow-list on save. | [MVP] |
| NFR-SEC-07 | Mass assignment MUST be controlled — no unguarded models; `role`, `status`, price and ownership fields MUST never be fillable from request input. | [MVP] |
| NFR-SEC-08 | Security headers MUST be sent: CSP, `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`/`frame-ancestors`, `Permissions-Policy`. | [MVP] |
| NFR-SEC-09 | Sessions MUST use secure, `HttpOnly`, `SameSite=Lax` cookies with a configured idle timeout and regeneration on privilege change. | [MVP] |
| NFR-SEC-10 | Rate limiting MUST be applied to: login, registration, password reset, activation resend, webhook, media access and assessment submission. | [MVP] |
| NFR-SEC-11 | Uploaded files MUST be validated (size, extension, MIME, content sniff), stored privately with generated names, and served only through authorised controllers. | [MVP] |
| NFR-SEC-12 | Uploaded content MUST never be executed by the web server; the storage location MUST be outside the document root. | [MVP] |
| NFR-SEC-13 | Payment webhooks MUST be signature-verified against the raw body before parsing, using a constant-time comparison. | [MVP] |
| NFR-SEC-14 | All secrets MUST come from environment variables. `.env` MUST be git-ignored. No secret may ever be committed, and any leaked secret MUST be rotated. | [MVP] |
| NFR-SEC-15 | `APP_DEBUG` MUST be `false` in production; errors MUST show generic pages while full detail goes to logs. | [MVP] |
| NFR-SEC-16 | Audit logs MUST record actor, action, entity, before/after where relevant, IP, user agent and timestamp for: auth events, role/status changes, course publish/unpublish, content deletion, enrollment grant/revoke, payment state changes, settings changes, and admin impersonation. | [MVP] |
| NFR-SEC-17 | Audit logs MUST be append-only from the application's perspective — no UI or service method may edit or delete them. | [MVP] |
| NFR-SEC-18 | Authorisation MUST be deny-by-default; a resource with no policy MUST be inaccessible rather than open. | [MVP] |
| NFR-SEC-19 | Direct object reference attacks MUST be neutralised — every record fetch by ID MUST be followed by a policy check. | [MVP] |
| NFR-SEC-20 | Dependencies MUST be scanned for known vulnerabilities in CI; critical advisories MUST be patched before release. | [MVP] |
| NFR-SEC-21 | Correct answers, marking keys and other assessment secrets MUST NEVER be present in any response sent before submission. | [MVP] |
| NFR-SEC-22 | Signed media URLs MUST be short-lived (default ≤ 5 minutes) and MUST be bound to the authorised user's session where technically possible. | [MVP] |
| NFR-SEC-23 | Automated dependency-update and security-advisory monitoring. | [V1.1] |
| NFR-SEC-24 | Third-party penetration test before public launch. | [V1.1] |

---

## 22. Future requirements — multi-organisation

These are **not built in Version 1.0**. They exist so the architecture can be judged against them.

| ID | Requirement | Priority |
|---|---|---|
| FR-SYS-F01 | The platform will host multiple independent organisations, each with its own courses, users, enrollments, payments and branding. | [FUTURE] |
| FR-SYS-F02 | Data belonging to one organisation MUST be unreachable from another under any circumstance. | [FUTURE] |
| FR-SYS-F03 | Organisations will be resolved by subdomain or path prefix. | [FUTURE] |
| FR-SYS-F04 | A Platform Owner role above Super Admin will manage organisations. | [FUTURE] |
| FR-SYS-F05 | Each organisation will have its own branding, sender identity and payment credentials. | [FUTURE] |
| FR-SYS-F06 | The existing single organisation MUST migrate to become organisation #1 with no data loss and no functional regression. | [FUTURE] |

**Architectural obligations accepted now to make the above cheap later** (each is a testable MVP requirement):

| ID | Requirement | Priority |
|---|---|---|
| FR-SYS-01 | Organisation-level configuration (name, logo, support email, currency, thresholds) MUST live in a `settings` table, never hardcoded in views or config files. | [MVP] |
| FR-SYS-02 | All storage paths MUST be produced by one path-resolver service so a tenant segment can be prefixed later. | [MVP] |
| FR-SYS-03 | All data access MUST go through Eloquent models and query scopes so a global tenant scope can be attached in one place per model. | [MVP] |
| FR-SYS-04 | Queued jobs MUST carry explicit context (model instances or IDs) and MUST NOT depend on ambient request state. | [MVP] |
| FR-SYS-05 | Uniqueness constraints that would become per-organisation later (course slug, category slug, setting key, order number) MUST be documented in `architecture.md` §24 as "composite-ready". | [MVP] |
| FR-SYS-06 | Email templates, branding and sender identity MUST be resolved through a service rather than read from `config()` at call sites. | [MVP] |
| FR-SYS-07 | No cross-course or cross-user data joins may assume a single global dataset in a way that a tenant filter cannot be added to. | [MVP] |

---

## 23. Acceptance criteria

Version 1.0 is accepted when every criterion below passes on a production-like environment with real (test-mode) Razorpay credentials.

### 23.1 Access control

| ID | Criterion |
|---|---|
| AC-01 | A guest can browse the catalogue and open a course detail page showing metadata only, and cannot open **any** lesson body, video, PDF, presentation, resource or assessment — including by direct URL. There is no preview exemption to test, because none exists. |
| AC-02 | A logged-in student who is not enrolled in course X receives 403 on every content, asset and assessment route of course X. |
| AC-03 | An instructor assigned to course A and not to course B receives 403 on every read and write route of course B, including assessment, student list, progress and report routes. |
| AC-04 | A student cannot read another student's progress, attempts, results, orders or payments by changing an ID in the URL. |
| AC-05 | No non-admin request can change a user's role or status. |
| AC-06 | The last active Super Admin cannot be deleted, deactivated or demoted. |

### 23.2 Purchase → enrollment

| ID | Criterion |
|---|---|
| AC-07 | A guest with no account can buy a course, receive a welcome-and-activate email, set a password via the one-time link, be logged in, and immediately see the course in My Courses. |
| AC-08 | An existing student buying a second course gets an enrollment against their existing account, a purchase-confirmation email, and **no** duplicate account. |
| AC-09 | Forging a browser-side "payment success" callback without a valid webhook creates **no** enrollment and grants **no** access. |
| AC-10 | A webhook request with an invalid signature is rejected with 400, is logged as a security event, and creates no records. |
| AC-11 | Replaying the same valid webhook event N times produces exactly one payment record, one enrollment and one email. |
| AC-12 | A webhook whose confirmed amount or currency does not match the order does not grant enrollment and raises an alert. |
| AC-13 | A payment whose webhook never arrives is settled by the reconciliation job within its scheduled interval, granting enrollment exactly once. |
| AC-14 | An activation link cannot be reused after a successful password set, and is rejected after expiry. |
| AC-15 | A refund webhook moves the enrollment to `refunded` and the student immediately loses content access. |

### 23.3 Content & delivery

| ID | Criterion |
|---|---|
| AC-16 | An admin can build a course with modules and lessons covering video, PDF, PPTX, resource, text and quiz, reorder them by drag-and-drop, and publish it. |
| AC-17 | Publishing is blocked with a clear message when the course fails publish validation. |
| AC-18 | An enrolled student can play a video, seek within it, and resume at the saved position on returning. |
| AC-19 | A media URL captured by an enrolled student stops working after its short TTL expires. |
| AC-20 | No learning asset is reachable at a predictable public path; the storage directory is not served by the web server. |
| AC-21 | Uploading a file with a disallowed extension, a spoofed MIME type, or an oversized body is rejected with a clear error and nothing is stored. |

### 23.4 Assessments

| ID | Criterion |
|---|---|
| AC-22 | An instructor creates a quiz with mixed question types, marks and a 70% pass mark on an assigned course; a student takes it and is graded correctly, including negative marking. |
| AC-23 | Correct answers are absent from every network response prior to submission (verified by inspecting responses). |
| AC-24 | A timed attempt submitted after the server-side deadline is rejected or auto-submitted with only the answers saved before the deadline. |
| AC-25 | The attempt limit is enforced server-side; a student cannot exceed it by replaying the start request. |
| AC-26 | A student cannot open two simultaneous in-progress attempts on the same assessment. |
| AC-27 | The answer-reveal policy is honoured in all three modes. |

### 23.5 Progress

| ID | Criterion |
|---|---|
| AC-28 | Completing lessons updates lesson, module and course progress consistently, and the dashboard reflects it without a full recompute. |
| AC-29 | "Continue learning" opens the exact lesson last accessed. |
| AC-30 | Adding a published lesson to a course recalculates progress for all its enrollments and lowers percentages correctly. |
| AC-31 | Course completion is recorded with a timestamp once all lessons are complete and the final test (if required) is passed. |
| AC-32 | Concurrent progress writes from two tabs do not corrupt state or revert a completed lesson. |

### 23.6 Operations & quality

| ID | Criterion |
|---|---|
| AC-33 | All emails are dispatched through the queue and a mail failure never rolls back or blocks an enrollment. |
| AC-34 | Audit entries exist for every login, role/status change, publish/unpublish, content deletion, enrollment grant/revoke, payment state change and settings change. |
| AC-35 | `APP_DEBUG=false`, HTTPS with HSTS, and all required security headers are confirmed present in production. |
| AC-36 | No secret exists anywhere in git history; `.env` is ignored; `.env.example` is complete and value-free. |
| AC-37 | The automated test suite passes, Pint reports no violations, and Larastan passes at the agreed level. |
| AC-38 | A database backup is successfully restored into a scratch environment as a rehearsal. |
| AC-39 | Every MVP requirement in this document maps to at least one automated or documented manual test. |

---

## 24. Assumptions

| ID | Assumption | Impact if false |
|---|---|---|
| A-01 | One organisation only in Version 1.0. | Multi-tenancy work must be pulled into the MVP — significant re-plan. |
| A-02 | Razorpay account exists with API keys and webhook capability, INR only. | Payment phase blocks. |
| A-03 | One-time purchase of a single paid course per order; no subscriptions, bundles, coupons or free courses in V1. | Order/pricing model changes. |
| A-03a | Guests see course metadata only; no learning content of any kind is publicly reachable. | The access gate gains a preview branch and the course detail page gains a content surface. |
| A-04 | Instructors do not author course content in V1 — only assessments. | Course Builder authorisation and Instructor phase expand. |
| A-05 | Video files are pre-encoded MP4/H.264 uploaded by the admin; no server-side transcoding. **Confirmed by PD-12.** | Media pipeline and infrastructure cost change materially. |
| A-06 | Typical video length ≤ ~500 MB; catalogue size is tens of courses, not thousands. | Upload strategy and storage sizing change. |
| A-07 | Content protection at "raise the cost of casual sharing" level is acceptable; DRM is not required. **Confirmed by PD-12** — private storage with short-lived signed URLs, no DRM, no commercial video platform in V1, media architecture kept abstract so a provider can be added later. | A commercial video platform must be procured. |
| A-08 | English-language, INR-priced, India-first audience. | i18n and multi-currency become MVP. |
| A-09 | A transactional email provider (SES/Postmark/Mailgun/etc.) will be available with a verified sending domain (SPF/DKIM/DMARC). | Deliverability of activation emails — the critical path of onboarding — is at risk. |
| A-10 | Production runs a persistent server (VPS/managed platform) able to run queue workers and a scheduler. | Serverless would require a different queue strategy. |
| A-11 | Redis is available in production for cache, session and queue. | Fall back to database drivers with reduced throughput. |
| A-12 | Students have broadband sufficient for progressive MP4 playback. | Adaptive bitrate becomes MVP. |
| A-13 | A single Super Admin seeded at deployment is sufficient to bootstrap the platform. | Bootstrap flow changes. |
| A-14 | Refunds are initiated manually in the Razorpay dashboard; the LMS only reacts to the resulting webhook. | Refund UI becomes MVP. |
| A-15 | Legal/GST invoicing is handled outside the LMS in V1. | Invoicing becomes MVP. |

---

## 25. Constraints

| ID | Constraint | Source |
|---|---|---|
| C-01 | Backend language: **PHP 8.5**. | Customer decision (PD-01) |
| C-02 | Framework: **Laravel 13.x**, pinned to the major version. The latest stable 13.x patch is used at installation; the major version never floats. | Customer decision (PD-01) |
| C-03 | Database: PostgreSQL 16+. | Customer decision |
| C-04 | Frontend: Laravel Blade + **Livewire 4** (server-driven; no SPA framework). | Customer decision (PD-10 stack) |
| C-05 | Styling: Tailwind CSS. | Customer decision |
| C-06 | Authentication: **Laravel Fortify** as the headless authentication backend, with a custom LMS-built UI. Authentication MUST NOT be hand-rolled, and the default starter-kit UI MUST NOT be adopted. No third-party identity provider. | Customer decision (PD-03) |
| C-06a | Testing framework: **Pest**. | Customer decision (PD-04) |
| C-07 | Payments: Razorpay in V1. All V1 courses are paid; there is no free-course path. | Customer decision |
| C-08 | File storage must work on local disk in development and S3-compatible storage in production, switchable by configuration. | Customer decision |
| C-09 | Email must use Laravel Mail. | Customer decision |
| C-10 | Queues must be used for email and background work. | Customer decision |
| C-11 | Multi-tenancy MUST NOT be implemented in V1, but the architecture MUST allow it later without a rewrite. | Customer decision |
| C-12 | Exactly three roles in V1: Super Admin, Instructor, Student. | Customer decision |
| C-13 | Deployment target is not yet chosen; the architecture must not depend on a specific host. | Customer decision |
| C-14 | Phases must be implemented one at a time; no phase may begin before the previous one meets its Definition of Done. | `planning.md` |
| C-15 | Razorpay operates in INR and requires a webhook-reachable public HTTPS endpoint; local development requires a tunnel for webhook testing. | Gateway constraint |
| C-16 | PHP upload limits (`upload_max_filesize`, `post_max_size`, `max_execution_time`) constrain direct large-file uploads and drive the chunked/direct-to-storage design. | Platform constraint |

---

## 26. Traceability

| Requirement area | Architecture section | Delivering phase(s) |
|---|---|---|
| Authentication (FR-AUTH) | `architecture.md` §7 | Phase 2 |
| RBAC (FR-RBAC) | `architecture.md` §8 | Phase 2 |
| Courses / Modules / Lessons (FR-CRS, FR-CNT) | `architecture.md` §6, §9 | Phases 3, 5 |
| Files & video (FR-FILE) | `architecture.md` §15, §16 | Phases 5, 6 |
| Enrollment (FR-ENR) | `architecture.md` §12 | Phase 6 *(engine + admin grant)*, Phase 12 *(payment as a source)* |
| Student experience (FR-STU) | `architecture.md` §5, §9 | Phase 7 *(learning)*, Phase 12 *(purchase, payment history)* |
| Assessments (FR-ASMT) | `architecture.md` §10 | Phase 8 |
| Progress (FR-PROG) | `architecture.md` §17 | Phase 9 |
| Instructor (FR-INS) | `architecture.md` §8 | Phase 10 |
| Email (FR-MAIL) | `architecture.md` §14 | Phase 11 *(infrastructure + templates)*, Phase 12 *(purchase onboarding flow, FR-MAIL-01)* |
| Payments (FR-PAY) | `architecture.md` §11 | Phase 12 |
| Reporting (FR-RPT) | `architecture.md` §19 | Phase 13 |
| Security (NFR-SEC) | `architecture.md` §18 | Every phase; audited in Phase 14 |
| Multi-organisation (FR-SYS) | `architecture.md` §24 | Prepared throughout; delivered in Phase 18 |
