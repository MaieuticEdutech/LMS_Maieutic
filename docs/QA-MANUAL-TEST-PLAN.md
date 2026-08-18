# QA MANUAL TEST PLAN — Admin & Instructor
### Phases 1–10 · every route, every component, every state

> Built from the actual route table, Livewire components and validation rules on
> `phase/10-instructor-module`. Every ID below is executable by hand in a browser.
>
> **How to use:** work top to bottom. §0 → §2 must pass before anything else is
> meaningful. Sections are ordered so that each one *creates the data* the next
> one needs — Admin builds the course, Instructor reads it, Student generates
> the attempts that Instructor's results screens display.
>
> **Legend:** ✅ pass · ❌ fail (log it) · ⚠️ pass with concern · ⏭️ blocked/skipped

---

## §0 — SETUP AND COLD-START PREREQUISITES

```bash
php artisan migrate:fresh --seed
php artisan storage:link          # only if not already linked
npm run dev                       # Vite, keep running
php artisan serve                 # http://127.0.0.1:8000
```

### Seeded accounts — password is `password` for all

| Role | Email | State |
|---|---|---|
| Super Admin | `admin@lms.test` | active, verified |
| Instructor | `instructor@lms.test` | active |
| Student | `student@lms.test` | active, verified, **no enrollments** |
| Student | `unverified@lms.test` | pending_verification — cannot log in |
| Student | `awaiting@lms.test` | pending_activation, NULL password |
| Student | `suspended@lms.test` | suspended — blocked at login |

### ⚠️ Known cold-start gaps — verify these first, they shape everything after

| ID | Check | Expected |
|---|---|---|
| **SETUP-01** | Fresh DB has **zero** courses, modules, lessons, assessments, enrollments | Confirmed empty — every course test below starts from creation |
| **SETUP-02** | `categories` table is empty AND there is **no admin UI to create a category** | Course Builder's category dropdown renders empty. **This is a finding** — log it. Workaround below |
| **SETUP-03** | Seed a category by hand so the dropdown can be tested at all | `php artisan tinker` → `App\Models\Category::create(['name'=>'Engineering','slug'=>'engineering','position'=>1]);` |
| **SETUP-04** | `LMS_CONTENT_DISK` is `content` (local) in `.env` | Media tests use the signed-route strategy, not S3 |
| **SETUP-05** | Mail driver is `log` or Mailpit | Activation/reset emails land somewhere inspectable |
| **SETUP-06** | `LMS_MAIL_PREVIEW_ENABLED=true` locally | `/dev/mail` renders; must 404 in production |
| **SETUP-07** | `GET /up` returns 200 with DB, cache and storage all reachable | Health probe honest, not just "PHP responded" |

---

## §1 — AUTHENTICATION AND SESSION (AUTH)

| ID | Steps | Expected |
|---|---|---|
| AUTH-01 | Login `admin@lms.test` / `password` | Lands on `/admin` dashboard, admin shell chrome |
| AUTH-02 | Login `instructor@lms.test` / `password` | Lands on instructor area, instructor shell |
| AUTH-03 | Login `student@lms.test` / `password` | Lands on `/dashboard`, public/app chrome |
| AUTH-04 | Login with correct email, wrong password | Generic failure. Must **not** say "password incorrect" vs "no such user" |
| AUTH-05 | Login `nobody@nowhere.test` | Identical message and timing to AUTH-04 — no user enumeration |
| AUTH-06 | Login `unverified@lms.test` | Blocked / redirected to verify-email notice. No dashboard content leaks |
| AUTH-07 | Login `suspended@lms.test` | Refused. Message says account suspended, not "wrong password" |
| AUTH-08 | Login `awaiting@lms.test` | Structurally impossible (NULL password). Refused cleanly, no 500 |
| AUTH-09 | Hit `/activate/{invalid-token}` | Graceful invalid-token page, never a stack trace |
| AUTH-10 | Request activation resend 6× rapidly | `throttle:activation-resend` kicks in with 429 |
| AUTH-11 | Forgot-password for a real address, then for a fake one | Identical response both times — no enumeration |
| AUTH-12 | Password reset link, set new password, log in | Succeeds; old password now rejected |
| AUTH-13 | Reset link reused a second time | Rejected |
| AUTH-14 | Rate-limit login: 6 bad attempts | Throttled with lockout message |
| AUTH-15 | Log in, log out, press browser Back | No cached authenticated page rendered |
| AUTH-16 | Log in as admin in tab A, log out in tab B, act in tab A | Redirected to login, action not executed |
| AUTH-17 | Session expiry (or clear cookie) then submit a Livewire action | Clean redirect to login, not a Livewire 419 crash dump |
| AUTH-18 | Submit any form with a stale CSRF token | 419 handled gracefully |
| AUTH-19 | Admin sets own account inactive via tinker, then navigates | `active` middleware ejects them immediately |
| AUTH-20 | `/profile` while logged in as **each** of the three roles | Reachable by all three — it sits outside the role groups |
| AUTH-21 | Profile: change name + phone, save | Persists, flash confirms, appears in header |
| AUTH-22 | Profile: change password with wrong current password | Rejected with field error |
| AUTH-23 | Profile: change password correctly, re-login | New password works |
| AUTH-24 | Profile: try to edit another user's profile by URL/ID tampering | UserPolicy denies (403) unless super admin |

---

## §2 — ROLE-BASED ACCESS CONTROL MATRIX (RBAC)

**This is the most important section in the document.** Run every cell. A single green
cell that should be red is a security defect, not a UI bug.

### 2.1 Guest (logged out) — every protected URL must redirect to login, never render

| ID | URL | Expected |
|---|---|---|
| RBAC-G01 | `/admin` | Redirect to login |
| RBAC-G02 | `/admin/students` | Redirect |
| RBAC-G03 | `/admin/instructors` | Redirect |
| RBAC-G04 | `/admin/courses` | Redirect |
| RBAC-G05 | `/admin/enrollments` | Redirect |
| RBAC-G06 | `/admin/assessments` | Redirect |
| RBAC-G07 | `/admin/settings` | Redirect |
| RBAC-G08 | `/admin/audit-log` | Redirect |
| RBAC-G09 | `/instructor` and all sub-routes | Redirect |
| RBAC-G10 | `/dashboard`, `/my-courses` | Redirect |
| RBAC-G11 | `/learn/{course}` | Redirect — **never** a lesson body |
| RBAC-G12 | `/media/{id}/url` | Redirect (auth runs before policy) |
| RBAC-G13 | `/media/{id}/stream` without a signature | 403 invalid signature |
| RBAC-G14 | `/courses` (catalogue) and `/courses/{slug}` | **200 — public by design.** Metadata only |
| RBAC-G15 | Catalogue detail page source: search for lesson body text, media URL, assessment | Absent. Titles and durations only (AC-01, ADR-014) |

### 2.2 Student attempting admin/instructor URLs — all must be 403

| ID | URL as `student@lms.test` | Expected |
|---|---|---|
| RBAC-S01 | `/admin` | 403 |
| RBAC-S02 | `/admin/students` | 403 |
| RBAC-S03 | `/admin/students/1/edit` | 403 |
| RBAC-S04 | `/admin/instructors` | 403 |
| RBAC-S05 | `/admin/courses` | 403 |
| RBAC-S06 | `/admin/courses/create` | 403 |
| RBAC-S07 | `/admin/enrollments` | 403 |
| RBAC-S08 | `/admin/enrollments/create` | 403 |
| RBAC-S09 | `/admin/settings` | 403 |
| RBAC-S10 | `/admin/audit-log` | 403 |
| RBAC-S11 | `/admin/assessments` | 403 |
| RBAC-S12 | `/admin/assessments/{id}` (builder) | 403 — a student must never see the builder |
| RBAC-S13 | `/admin/assessments/{id}/results` | 403 |
| RBAC-S14 | `/instructor` | 403 |
| RBAC-S15 | `/instructor/courses` | 403 |
| RBAC-S16 | `/instructor/assessments/{id}/results` | 403 |

### 2.3 Instructor attempting admin URLs — all must be 403

| ID | URL as `instructor@lms.test` | Expected |
|---|---|---|
| RBAC-I01 | `/admin` | 403 |
| RBAC-I02 | `/admin/students` | 403 |
| RBAC-I03 | `/admin/instructors` | 403 — cannot manage peers |
| RBAC-I04 | `/admin/courses` | 403 — instructors do **not** author courses (FR-INS-08) |
| RBAC-I05 | `/admin/courses/create` | 403 |
| RBAC-I06 | `/admin/courses/{id}/builder` | 403 |
| RBAC-I07 | `/admin/enrollments` | 403 |
| RBAC-I08 | `/admin/enrollments/create` | 403 — cannot grant themselves access |
| RBAC-I09 | `/admin/settings` | 403 |
| RBAC-I10 | `/admin/audit-log` | 403 |
| RBAC-I11 | `/dashboard`, `/my-courses`, `/learn/{course}` | 403 — student area is not theirs |
| RBAC-I12 | Instructor nav sidebar contains exactly 3 items | Dashboard, My courses, Assessments. **No** Students, Enrolments, Settings, Audit |
| RBAC-I13 | Search every instructor page for any money figure (₹, price, revenue, order) | **Zero occurrences** (FR-INS-10) |

### 2.4 Admin attempting instructor/student URLs

| ID | URL as `admin@lms.test` | Expected |
|---|---|---|
| RBAC-A01 | `/instructor` | 403 — `role:instructor` is exclusive |
| RBAC-A02 | `/dashboard` (student home) | 403 |
| RBAC-A03 | `/admin/assessments/{id}` builder | 200 — shared component, admin chrome |
| RBAC-A04 | `/admin/assessments/{id}/results` | 200 — admin chrome, not instructor chrome |

### 2.5 Cross-tenant / object-level (IDOR) — the checks middleware cannot make

| ID | Steps | Expected |
|---|---|---|
| RBAC-X01 | Instructor B opens `/instructor/courses/{id}` for a course assigned to **Instructor A only** | 403/404. Never renders |
| RBAC-X02 | Instructor B opens `/instructor/assessments/{id}` for A's course assessment | 403 |
| RBAC-X03 | Instructor B opens `/instructor/assessments/{id}/results` for A's assessment | 403 |
| RBAC-X04 | Instructor B opens `/instructor/courses/{A's course}/students/{A's enrollment}` | 403 |
| RBAC-X05 | Instructor A opens their own course but swaps the `{enrollment}` id for one from a **different** course | 403/404 — the pair must be validated, not just each part |
| RBAC-X06 | Student opens `/attempts/{another student's attempt ulid}/result` | 403 |
| RBAC-X07 | Student opens `/assessments/{id}/attempt` for a course they are **not** enrolled in | 403 via `EnrollmentAccessService` |
| RBAC-X08 | Student with a **suspended** enrollment opens the player | Access denied |
| RBAC-X09 | Student with an **expired** enrollment opens the player | Access denied |
| RBAC-X10 | Student with a **completed** enrollment opens the player | **Allowed** — completed still grants access |
| RBAC-X11 | Student requests `/media/{id}/url` for another course's media | 403 |
| RBAC-X12 | Mint a media URL, revoke the enrollment, then use the URL within the TTL | **Denied** — controller re-checks the policy, signature alone is not entitlement |

---

## §3 — ADMIN SHELL AND NAVIGATION (SHELL)

| ID | Check | Expected |
|---|---|---|
| SHELL-01 | Sidebar shows 8 items in order | Dashboard, Students, Instructors, Courses, Enrolments, Assessments, Settings, Audit log |
| SHELL-02 | Click each of the 8 | All resolve 200, none 404 |
| SHELL-03 | Active-state highlight on each page | Exactly one item highlighted; matches current section |
| SHELL-04 | Active state on a **sub**-page (`/admin/students/1/edit`) | "Students" still highlighted (`routeIs('...*')`) |
| SHELL-05 | Breadcrumbs on every admin page | Present, last crumb is unlinked, parents navigate correctly |
| SHELL-06 | Resize to 375px wide | Sidebar collapses into a drawer; hamburger appears |
| SHELL-07 | Open drawer, click a link | Navigates and drawer closes |
| SHELL-08 | Drawer and desktop sidebar show identical items | Shared partial — no drift |
| SHELL-09 | Escape key with drawer open | Drawer closes |
| SHELL-10 | Keyboard-only: Tab through nav | Visible focus ring on every item, logical order |
| SHELL-11 | Flash message region | Appears after any save; dismissible; does not shift layout |
| SHELL-12 | Two flashes in a row | Second replaces/stacks cleanly, no overlap |
| SHELL-13 | Org name in sidebar/header | Comes from Settings, not hardcoded (change it in §11 and re-check) |
| SHELL-14 | Browser tab title per page | Distinct and meaningful, not all identical |
| SHELL-15 | Instructor shell | Same treatment, "Instructor" eyebrow, 3 nav items |
| SHELL-16 | 404 page inside admin area | Styled, keeps chrome, not a raw Laravel error |
| SHELL-17 | 403 page | Styled and explains, not a stack trace |

---

## §4 — ADMIN DASHBOARD (AD)

| ID | Check | Expected |
|---|---|---|
| AD-01 | Load `/admin` on a **fresh** DB | Renders with zeroes, **no** division-by-zero, no "NaN", no crash |
| AD-02 | Each KPI tile | Has a label, a value, and is not clipped |
| AD-03 | Student count vs `/admin/students` total | Numbers agree |
| AD-04 | Instructor count vs `/admin/instructors` | Agree |
| AD-05 | Course count vs `/admin/courses` | Agree |
| AD-06 | Enrollment count vs `/admin/enrollments` | Agree |
| AD-07 | Suspended/inactive users are counted per their documented definition | Consistent with the tables' filters |
| AD-08 | After creating a course in §7, reload | Counts increment |
| AD-09 | After granting an enrollment in §10, reload | Enrollment KPI increments |
| AD-10 | Any "recent activity" list | Newest first, links resolve |
| AD-11 | Empty recent-activity list | Renders `x-empty-state`, not a bare table head |
| AD-12 | Tile links (if clickable) | Land on the correctly pre-filtered table |
| AD-13 | Dashboard at 375px | Tiles stack, nothing overflows horizontally |
| AD-14 | Page load time with seed data | No obvious N+1 stall; check `DB::listen`/Debugbar if available |

---

## §5 — STUDENT MANAGEMENT (STU)

### 5.1 Students table — `/admin/students`

| ID | Check | Expected |
|---|---|---|
| STU-01 | Initial load | 5 seeded students listed with name, email, status, created |
| STU-02 | Search "Dev" | Filters to matching rows, resets to page 1 |
| STU-03 | Search an email fragment `@lms.test` | Matches on email column too |
| STU-04 | Search gibberish `zzzzz` | `x-empty-state` with a clear "no results" message, **not** a blank table |
| STU-05 | Search `'; DROP TABLE users;--` | Treated as a literal string, zero results, table still exists |
| STU-06 | Search `<script>alert(1)</script>` | Rendered escaped in the "no results for…" echo, no alert fires |
| STU-07 | Status filter → each of active / inactive / suspended / pending_verification / pending_activation | Each returns only that status |
| STU-08 | Combine search + status filter | Both applied (AND), not one overriding the other |
| STU-09 | Sort by name | Ascending; click again → descending; arrow indicator flips |
| STU-10 | Sort by email, by created date, by status | Each sorts correctly both directions |
| STU-11 | Sort while a filter is active | Filter survives the sort |
| STU-12 | Reset filters button | Clears search, filters and sort; returns to page 1 |
| STU-13 | Pagination (create 30+ students first, or lower per-page) | Page 2 loads, links work, current page marked |
| STU-14 | Change page, then search | Returns to page 1 automatically (`updatingSearch`) |
| STU-15 | Filters survive a browser refresh (URL query string) | Query params present and re-applied |
| STU-16 | Deep-link a filtered URL in a new tab | Same filtered result |
| STU-17 | Export button (`requestExport`) | Fires without error; produces a file or a clear "queued" flash |
| STU-18 | Row click / "View" link | Opens the correct student detail |
| STU-19 | Status badges | Colour-coded and legible; `x-badge` variant per status |
| STU-20 | Table at 375px | Horizontal scroll or stacked cards — no clipped content |

### 5.2 Create student — `/admin/students/create`

| ID | Check | Expected |
|---|---|---|
| STU-21 | Submit completely empty | `name` and `email` both show required errors; nothing saved |
| STU-22 | Name = 256 chars | Rejected (`max:255`) |
| STU-23 | Name = 255 chars | Accepted — boundary |
| STU-24 | Email = `not-an-email` | Rejected |
| STU-25 | Email = `admin@lms.test` (existing) | Rejected as already taken |
| STU-26 | Email = `ADMIN@LMS.TEST` (case) | Confirm the intended behaviour — duplicate or allowed. **Log whichever surprises you** |
| STU-27 | Phone = 31 chars | Rejected (`max:30`) |
| STU-28 | Phone left blank | Accepted (nullable) |
| STU-29 | Valid submit | Redirects to list/detail, flash confirms, row appears |
| STU-30 | Created user's role | `student` — never selectable/injectable to another role |
| STU-31 | Created user's status | Pending activation (no password set), **not** active |
| STU-32 | Activation email dispatched | Visible in the mail log |
| STU-33 | Try posting a `role` or `status` field via devtools | Ignored — not fillable (NFR-SEC-07) |
| STU-34 | Whitespace-only name `"   "` | Rejected |
| STU-35 | Unicode name `Śrīvatsa 中文` | Accepted and displayed correctly |
| STU-36 | XSS name `<img src=x onerror=alert(1)>` | Stored, rendered **escaped** everywhere it appears |
| STU-37 | Double-click Save fast | Only one user created |

### 5.3 Edit student — `/admin/students/{id}/edit`

| ID | Check | Expected |
|---|---|---|
| STU-38 | Form pre-populates with current values | Name, email, phone all correct |
| STU-39 | Save unchanged | Succeeds; no duplicate-email error against itself (`ignore()`) |
| STU-40 | Change email to another user's | Rejected |
| STU-41 | Change name only | Saves; audit log records the change |
| STU-42 | Edit URL with a **non-existent** id | 404 |
| STU-43 | Edit URL with an **instructor's** id | Confirm behaviour — should not silently edit an instructor via the student form |
| STU-44 | Cancel button | Returns without saving |

### 5.4 Student detail + lifecycle actions — `/admin/students/{id}`

| ID | Check | Expected |
|---|---|---|
| STU-45 | Detail page renders | Profile block, status, enrollments, activity |
| STU-46 | `changeStatus` → suspend an active student | Status flips, flash confirms, badge updates |
| STU-47 | Suspended student then tries to log in | Blocked |
| STU-48 | `changeStatus` → reactivate | Login works again |
| STU-49 | `changeStatus` → inactive | Same, and `active` middleware ejects a live session |
| STU-50 | Pass an **invalid** status string via devtools | Rejected — enum guarded, no DB write |
| STU-51 | `resendActivation` on a pending_activation account | Email sent, flash confirms |
| STU-52 | `resendActivation` on an **already active** account | Sensible behaviour or a clear refusal — **log it if it silently sends** |
| STU-53 | `forcePasswordReset` | Reset email sent; confirm whether existing sessions are invalidated |
| STU-54 | `delete` a student with **no** enrollments | Confirmation modal first; then deleted; redirect to list |
| STU-55 | Cancel the delete modal | Nothing deleted |
| STU-56 | `delete` a student **with** enrollments and attempts | Either blocked with a clear reason, or cascades intentionally. **No orphan rows, no FK 500** |
| STU-57 | Delete, then hit the detail URL again | 404 |
| STU-58 | Every action above appears in `/admin/audit-log` | Actor, action, target, timestamp all correct |
| STU-59 | Admin attempts to delete **their own** account | Blocked — you cannot delete yourself |
| STU-60 | Admin attempts to suspend **their own** account | Blocked or clearly warned |

---

## §6 — INSTRUCTOR MANAGEMENT (IM)

### 6.1 Table and form

| ID | Check | Expected |
|---|---|---|
| IM-01 | `/admin/instructors` lists the seeded instructor | Name, email, status, assigned-course count |
| IM-02 | Search / sort / filter / reset / paginate | Same battery as STU-02…STU-16 |
| IM-03 | Create: empty submit | `name`, `email` required |
| IM-04 | Create: `headline` 256 chars | Rejected (`max:255`) |
| IM-05 | Create: `bio` 2001 chars | Rejected (`max:2000`) |
| IM-06 | Create: `bio` exactly 2000 | Accepted — boundary |
| IM-07 | Create: duplicate email | Rejected |
| IM-08 | Create: valid | Instructor + `instructor_profile` row both created |
| IM-09 | Created role | `instructor`, status pending activation |
| IM-10 | Edit: pre-populated including headline/bio | Correct |
| IM-11 | Edit: clear bio to empty | Accepted (nullable) |
| IM-12 | Edit: change email to an existing one | Rejected |
| IM-13 | Bio with newlines and unicode | Preserved and escaped on render |
| IM-14 | Bio with HTML `<b>bold</b>` | Escaped, not rendered as markup |

### 6.2 Course↔instructor assignment (embedded in instructor detail)

| ID | Check | Expected |
|---|---|---|
| IM-15 | Detail page shows "assigned" and "available" course lists | Disjoint — a course never appears in both |
| IM-16 | With zero courses in the system | Empty state, no crash |
| IM-17 | `assign` a course | Moves from available → assigned instantly; flash confirms |
| IM-18 | Re-assign the **same** course (double click) | No duplicate pivot row; no unique-constraint 500 |
| IM-19 | `unassign` | Moves back to available |
| IM-20 | Unassign a course the instructor has assessments in | Confirm intended behaviour — instructor loses access. **Log it** |
| IM-21 | Assign, then log in as that instructor | Course now visible under My courses |
| IM-22 | Unassign, then refresh the instructor's course list | Course gone; direct URL now 403 |
| IM-23 | Assign 5 courses | All 5 listed; count on the table updates |
| IM-24 | Assign/unassign appears in audit log | Yes, with both ids |
| IM-25 | Tamper `courseId` to a non-existent id | Rejected, no 500 |
| IM-26 | Delete an instructor who has assigned courses | Blocked or cascades cleanly — courses must not be orphaned |
| IM-27 | Suspend an instructor mid-session | Ejected by `active` middleware |

---

## §7 — COURSES AND COURSE BUILDER (CRS)

### 7.1 Courses table — `/admin/courses`

| ID | Check | Expected |
|---|---|---|
| CRS-01 | Empty state on fresh DB | `x-empty-state` with a "create your first course" CTA |
| CRS-02 | `statusCounts` chips | Draft / Published / Archived counts correct and clickable |
| CRS-03 | Status filter each of the 3 | Correct subsets |
| CRS-04 | Search by title | Matches |
| CRS-05 | Sort by title / created / status | Both directions |
| CRS-06 | Counts update after publishing a course | Live and accurate |
| CRS-07 | Row shows price formatted as currency | From `Money` — integer paise rendered as ₹, never a float artefact like `199.99000001` |
| CRS-08 | Pagination + filters combined | As §5.1 |

### 7.2 Course Builder — create (`/admin/courses/create`)

| ID | Check | Expected |
|---|---|---|
| CRS-09 | Empty submit | `title`, `level`, `language`, `priceRupees` all required |
| CRS-10 | Title 256 chars | Rejected |
| CRS-11 | Subtitle 501 chars | Rejected (`max:500`) |
| CRS-12 | `priceRupees` = `0` | **Rejected** (`min:0.01`) — free courses unsupported |
| CRS-13 | `priceRupees` = `-100` | Rejected |
| CRS-14 | `priceRupees` = `0.01` | Accepted — boundary |
| CRS-15 | `priceRupees` = `abc` | Rejected (numeric) |
| CRS-16 | `priceRupees` = `199.999` | Confirm rounding to paise is defined, not silently truncated. **Log it** |
| CRS-17 | `priceRupees` = `99999999` | Confirm no integer overflow in paise |
| CRS-18 | `level` tampered to `expert-plus` | Rejected by `Rule::enum` |
| CRS-19 | `language` 11 chars | Rejected (`max:10`) |
| CRS-20 | `category_id` = non-existent id | Rejected (`exists`) |
| CRS-21 | `category_id` blank | Accepted (nullable) |
| CRS-22 | Valid create | Redirects into the builder for the new course; status `draft` |
| CRS-23 | Slug generated from title | Present, URL-safe |
| CRS-24 | Create a second course with the **same title** | Slug uniquified, no crash |
| CRS-25 | `addOutcome` × 5 | Five inputs appear |
| CRS-26 | `removeOutcome` middle index | Correct one removed; remaining re-indexed cleanly |
| CRS-27 | Outcome 256 chars | Rejected (`max:255`) |
| CRS-28 | Outcome left blank then saved | Nullable — confirm blanks are stripped, not stored as `""` |
| CRS-29 | `addRequirement` / `removeRequirement` | Same battery |
| CRS-30 | Remove **all** outcomes | Saves with an empty array, no crash |
| CRS-31 | Unsaved changes then navigate away | Confirm behaviour — warn or lose. **Log it** |

### 7.3 Course Builder — publish lifecycle

| ID | Check | Expected |
|---|---|---|
| CRS-32 | `publishBlockers` on a brand-new empty course | Lists **all** of: needs a description · needs a thumbnail image · needs a price above zero (if applicable) · needs at least one published module · needs at least one published lesson |
| CRS-33 | Publish button while blockers exist | Disabled, or refuses with the blocker list — **never** publishes |
| CRS-34 | Add a description → re-check blockers | That blocker disappears; others remain |
| CRS-35 | Upload a thumbnail → re-check | Thumbnail blocker clears |
| CRS-36 | Add a module but **no lessons** | Blocker reads `Module "X" has no published lessons.` with the real title |
| CRS-37 | Add an **unpublished** lesson only | Still blocked — unpublished doesn't count |
| CRS-38 | Publish an **unpublished module** with published lessons inside | Course still blocked on "at least one published module" |
| CRS-39 | Satisfy every blocker → publish | Succeeds; status → `published`; flash confirms |
| CRS-40 | Published course appears in the public catalogue `/courses` | Yes |
| CRS-41 | Draft course is **absent** from the public catalogue | Yes — and its `/courses/{slug}` URL 404s for a guest |
| CRS-42 | `unpublish` | Back to draft; vanishes from catalogue |
| CRS-43 | Unpublish a course with **active enrollments** | Enrolled students **keep** access via the player — confirm and log the intended rule |
| CRS-44 | `archive` a course | Status `archived`; out of catalogue; confirm enrolled-student behaviour |
| CRS-45 | Archived course still appears in admin with the Archived filter | Yes |
| CRS-46 | Publish → unpublish → publish again | Idempotent, no duplicate audit noise |
| CRS-47 | `delete` a course with no enrollments | Confirm modal, then deleted; modules/lessons cascade |
| CRS-48 | `delete` a course **with** enrollments | Blocked with a clear reason (or an explicit, audited cascade). **No orphan enrollments** |
| CRS-49 | Delete a course that has assessments | Assessments cascade or block — no dangling assessment |
| CRS-50 | Every lifecycle action in the audit log | Actor, action, course id |
| CRS-51 | Builder URL for a non-existent course id | 404 |
| CRS-52 | Two admins edit the same course in two tabs, both save | Last write wins without data corruption — log if silently destructive |

### 7.4 Modules (ModuleList)

| ID | Check | Expected |
|---|---|---|
| MOD-01 | `openCreate` modal | Opens with empty fields |
| MOD-02 | Save empty title | Required error inside the modal |
| MOD-03 | Save valid module | Appears in the list at the end; modal closes; flash |
| MOD-04 | `openEdit` | Pre-populated with that module's values |
| MOD-05 | Edit title and save | Updates in place |
| MOD-06 | `moveModule` up on the **first** module | No-op, no error, no position corruption |
| MOD-07 | `moveModule` down on the **last** | No-op |
| MOD-08 | Move middle module up | Swaps correctly; positions stay contiguous |
| MOD-09 | Reorder by drag (`reorder`) | New order persists across a refresh |
| MOD-10 | `reorder` with a tampered id array containing a foreign module id | Rejected — no cross-course reordering |
| MOD-11 | Publish/unpublish a module | Reflected in course publish blockers |
| MOD-12 | `confirmDelete` → cancel | Nothing deleted |
| MOD-13 | `delete` a module containing lessons | Lessons cascade (confirm intended); no orphans |
| MOD-14 | Delete the only module of a published course | Course either auto-unpublishes or is flagged — **log the behaviour** |
| MOD-15 | Create 20 modules | List stays usable; positions correct |
| MOD-16 | `refreshCourse` after a child change | Parent totals (lesson count, duration) update |

### 7.5 Lessons (LessonList + LessonEditor)

| ID | Check | Expected |
|---|---|---|
| LSN-01 | `selectableTypes` offers all six | video, document, presentation, text, resource, quiz |
| LSN-02 | Create a lesson of **each** of the six types | All six save; correct editor partial loads for each |
| LSN-03 | Empty title | Required error |
| LSN-04 | `editLesson` loads the right editor partial per type | `video` → video editor, `text` → text editor, etc. |
| LSN-05 | Text lesson: save body content | Persists and renders in the student player |
| LSN-06 | Text lesson: body with HTML/script | Confirm sanitisation policy; must not execute in the player |
| LSN-07 | Video lesson without media | Cannot publish — surfaced as a blocker |
| LSN-08 | Video lesson: duration field | Feeds course total duration |
| LSN-09 | Quiz lesson: `createAssessment` | Creates the assessment and redirects into the Assessment Builder |
| LSN-10 | Quiz lesson: `createAssessment` **twice** | No duplicate assessment; second click goes to the existing one |
| LSN-11 | `assessment()` accessor on a quiz lesson | Returns the linked assessment, not null after creation |
| LSN-12 | Resource lesson: attach a downloadable file | Appears with a download affordance |
| LSN-13 | `moveLesson` first-up / last-down | No-ops |
| LSN-14 | `reorder` lessons | Persists |
| LSN-15 | `reorder` with an id from a **different module** | Rejected |
| LSN-16 | Publish/unpublish a lesson | Reflected in module and course blockers |
| LSN-17 | `confirmDelete` → `delete` | Removed; positions re-flow contiguously |
| LSN-18 | Delete a quiz lesson that owns an assessment | Assessment handled explicitly, not orphaned |
| LSN-19 | Delete a lesson a student has progress on | No FK error; progress handled |
| LSN-20 | Lesson `is_free_preview` (if present) | Confirm it does **not** leak content publicly — ADR-014 says no preview exemption in V1 |

### 7.6 Media upload (MediaUploader)

| ID | Check | Expected |
|---|---|---|
| MED-01 | Upload a valid JPG thumbnail | Succeeds, preview renders |
| MED-02 | Upload a valid MP4 to a video lesson | Succeeds |
| MED-03 | Upload a valid PDF to a document lesson | Succeeds |
| MED-04 | Upload a `.exe` renamed to `.pdf` | **Rejected** — MIME sniffed, not extension-trusted |
| MED-05 | Upload a `.php` file | Rejected |
| MED-06 | Upload an oversized file (above the configured cap) | Rejected with a readable message, not a 413 white screen |
| MED-07 | Upload a 0-byte file | Rejected |
| MED-08 | Upload with a filename containing `../` or unicode | Stored safely; no path traversal |
| MED-09 | `MediaValidationException` path | Renders as a field error on `file`, not an exception page |
| MED-10 | Single-mode uploader: upload a second file | **Replaces** the first (`ReplaceMedia`), old file cleaned up |
| MED-11 | Multi-mode uploader (`$multiple`): upload 3 files | All 3 attach |
| MED-12 | `confirmRemove` → `remove` | Detaches and deletes the stored object |
| MED-13 | Uploaded file's public URL guessed directly (`/storage/...`) | **404/403** — the content disk is private, not symlinked |
| MED-14 | `/media/{id}/url` as the owning admin | Returns a signed URL |
| MED-15 | That signed URL used immediately | Streams |
| MED-16 | Same URL after >300s | **Expired** (NFR-SEC-22) |
| MED-17 | Tamper one character of the signature | Rejected |
| MED-18 | Video seek (HTTP Range) | Seeking works — server honours `Range` |
| MED-19 | Download response headers | `Content-Disposition: attachment` + `X-Content-Type-Options: nosniff` |
| MED-20 | Hammer `/media/{id}/url` 100× | `throttle:media` returns 429 |
| MED-21 | Media access appears in the audit log | Yes, throttled so it doesn't flood |
| MED-22 | Upload progress indicator | Visible during a large upload; no frozen UI |
| MED-23 | Cancel/navigate away mid-upload | No partial record left behind |

---

## §8 — ASSESSMENTS (ASM) — admin and instructor share these screens

### 8.1 Assessments table

| ID | Check | Expected |
|---|---|---|
| ASM-01 | `/admin/assessments` on a fresh DB | Empty state |
| ASM-02 | `attachPointLabel` for a lesson-attached quiz | Shows the lesson (and course) name, not a bare id |
| ASM-03 | `attachPointLabel` for a course-attached test | Shows the course name |
| ASM-04 | `updatingTypeFilter` → quiz | Only quizzes |
| ASM-05 | Type filter → test | Only tests |
| ASM-06 | Search by assessment title | Matches |
| ASM-07 | Sort columns | Both directions |
| ASM-08 | Instructor's `/instructor/assessments` | Shows **only** assessments on their assigned courses |
| ASM-09 | Instructor list vs admin list | Instructor's is a strict subset |
| ASM-10 | Unassign the instructor's course, reload their list | Assessment disappears |

### 8.2 Assessment Builder — settings

| ID | Check | Expected |
|---|---|---|
| ASM-11 | Empty title | Required |
| ASM-12 | Title 256 chars | Rejected |
| ASM-13 | `passing_percentage` = `-1` | Rejected (`min:0`) |
| ASM-14 | `passing_percentage` = `101` | Rejected (`max:100`) |
| ASM-15 | `passing_percentage` = `0` and `100` | Both accepted — boundaries |
| ASM-16 | `passing_percentage` = `55.5` | Rejected (integer) |
| ASM-17 | `time_limit_minutes` = `0` | Rejected (`min:1`) |
| ASM-18 | `time_limit_minutes` blank | Accepted — means untimed |
| ASM-19 | `max_attempts` = `0` | Rejected (`min:1`) |
| ASM-20 | `max_attempts` blank | Accepted — means unlimited |
| ASM-21 | `scoring_policy` each of highest / latest / first | All three save |
| ASM-22 | `scoring_policy` tampered to `average` | Rejected (`in:`) |
| ASM-23 | `answer_reveal` each of never / after_submit / after_pass | All three save |
| ASM-24 | `answer_reveal` tampered value | Rejected |
| ASM-25 | Save with valid values | Flash confirms; values persist across refresh |
| ASM-26 | Builder loaded by an **instructor** | Instructor chrome + breadcrumbs, same functionality |
| ASM-27 | Builder loaded by **admin** | Admin chrome |
| ASM-28 | Instructor opens a builder for an unassigned course's assessment | 403 |

### 8.3 Questions (QuestionList + QuestionEditor)

| ID | Check | Expected |
|---|---|---|
| Q-01 | `openCreate` for each of the 4 types | single_choice, multiple_choice, true_false, short_answer — each opens its own editor |
| Q-02 | Empty body | Required |
| Q-03 | Body 2001 chars | Rejected (`max:2000`) |
| Q-04 | `marks` = `0` | Rejected (`min:0.01`) |
| Q-05 | `marks` = `-5` | Rejected |
| Q-06 | `marks` = `0.01` | Accepted — boundary |
| Q-07 | `negative_marks` = `-1` | Rejected (`min:0`) |
| Q-08 | `negative_marks` = `0` | Accepted |
| Q-09 | `explanation` 2001 chars | Rejected |
| Q-10 | **single_choice** with only 1 option | Rejected (`options min:2`) |
| Q-11 | single_choice with 2 options, none marked correct | Rejected — an answer key is mandatory |
| Q-12 | `markCorrectOption` on single_choice | Marking B **unmarks** A — exactly one correct |
| Q-13 | **multiple_choice** with 2+ correct | Allowed |
| Q-14 | multiple_choice with **zero** correct | Rejected |
| Q-15 | `addOption` × 6 | Six option rows |
| Q-16 | `removeOption` on the correct one | Correct flag handled — not left pointing at a removed index |
| Q-17 | `removeOption` down to 1 option then save | Rejected |
| Q-18 | Option body 501 chars | Rejected (`max:500`) |
| Q-19 | Option body blank | Rejected (required) |
| Q-20 | **true_false**: option set is fixed | Cannot add a third option |
| Q-21 | true_false: pick True, save, reopen | Selection persisted |
| Q-22 | **short_answer** with zero accepted answers | Rejected (`min:1`) |
| Q-23 | `addAcceptedAnswer` × 3 | Three inputs |
| Q-24 | Accepted answer 256 chars | Rejected (`max:255`) |
| Q-25 | `removeAcceptedAnswer` | Removes the right index |
| Q-26 | Duplicate accepted answers | Confirm behaviour — dedupe or allow. **Log it** |
| Q-27 | Save a valid question of each type | All four appear in the list with type badges |
| Q-28 | `moveQuestion` first-up / last-down | No-ops |
| Q-29 | `moveQuestion` middle | Swaps; positions contiguous |
| Q-30 | `reorder` drag | Persists across refresh |
| Q-31 | `reorder` with a question id from **another assessment** | Rejected |
| Q-32 | `confirmDelete` → cancel → `delete` | Only deletes on confirm; options cascade |
| Q-33 | Delete a question a student has already answered | No FK 500; handled explicitly |
| Q-34 | Total marks recalculates after add/edit/delete | Matches the sum of question marks |
| Q-35 | Question body with `<script>` | Escaped on render everywhere including the student runner |
| Q-36 | Question body with unicode/maths symbols | Renders correctly |
| Q-37 | 50 questions on one assessment | List stays usable; reorder still correct |

### 8.4 🔴 The answer-key leak test — NFR-SEC-21 / AC-23

| ID | Check | Expected |
|---|---|---|
| Q-38 | As a student, open the attempt runner and **View Source** | `is_correct` appears **nowhere** |
| Q-39 | Inspect the Livewire component payload in DevTools → Network | No `is_correct`, no accepted-answer strings |
| Q-40 | Inspect `wire:snapshot` in the DOM | Answer key absent |
| Q-41 | With `answer_reveal = never`, view the result page | Correct answers **never** shown |
| Q-42 | With `after_pass`, **fail** the attempt | Answers hidden |
| Q-43 | With `after_pass`, **pass** the attempt | Answers shown |
| Q-44 | With `after_submit`, submit | Answers shown |
| Q-45 | Short-answer accepted answers in the runner payload | Absent |

### 8.5 Assessment publish and delete

| ID | Check | Expected |
|---|---|---|
| ASM-29 | `publishBlockers` with zero questions | `The assessment needs at least one question.` |
| ASM-30 | Blockers with all questions at 0 total marks | `The assessment needs a total marks value above zero.` |
| ASM-31 | Blockers with an out-of-range passing percentage | `The passing percentage must be between 0 and 100.` |
| ASM-32 | Publish while blocked | Refused |
| ASM-33 | Publish when clean | Succeeds; visible to enrolled students |
| ASM-34 | `unpublish` with an **in-progress** student attempt | Confirm the rule — is the attempt allowed to finish? **Log it** |
| ASM-35 | `delete` an assessment with attempts | Blocked or cascades intentionally; no orphan attempts/answers |
| ASM-36 | Delete with zero attempts | Clean; questions and options cascade |
| ASM-37 | Instructor publishes an assessment on their own course | Allowed |
| ASM-38 | Instructor deletes an assessment on their own course | Confirm whether allowed; log the policy decision |

---

## §9 — ENROLLMENTS (ENR)

### 9.1 Grant enrollment — `/admin/enrollments/create`

| ID | Check | Expected |
|---|---|---|
| ENR-01 | Empty submit | `studentId`, `courseId`, `reason` all required |
| ENR-02 | `reason` = `ab` | Rejected (`min:3`) |
| ENR-03 | `reason` = `abc` | Accepted — boundary |
| ENR-04 | `reason` = 501 chars | Rejected (`max:500`) |
| ENR-05 | Student dropdown | Lists students only — **no** admins or instructors |
| ENR-06 | Course dropdown | Lists eligible courses |
| ENR-07 | `studentId` tampered to an **instructor's** id | Rejected |
| ENR-08 | `courseId` tampered to a non-existent id | Rejected (`exists`) |
| ENR-09 | Valid grant | Enrollment created with source `admin_grant`, status `active` |
| ENR-10 | Grant the **same** student+course twice | Blocked as duplicate — no double enrollment |
| ENR-11 | Grant appears in the audit log **with the reason text** | Yes — the reason is the whole point of the field |
| ENR-12 | Student logs in immediately after | Course appears in My Courses; player opens |
| ENR-13 | Grant on an **archived** course | Confirm intended behaviour; log it |
| ENR-14 | Grant on a **draft** course | Confirm intended behaviour; log it |
| ENR-15 | Double-click Grant | One enrollment, not two |

### 9.2 Enrollments table and state actions

| ID | Check | Expected |
|---|---|---|
| ENR-16 | Table lists the new enrollment | Student, course, status, source, granted-at |
| ENR-17 | `summary()` counts | Match the row counts per status |
| ENR-18 | `updatingStatusFilter` → each of active / suspended / completed / expired / refunded | Correct subsets |
| ENR-19 | `updatingSourceFilter` → purchase / admin_grant / import | Correct subsets |
| ENR-20 | `updatingCourseFilter` via `courseOptions` | Filters to one course |
| ENR-21 | All three filters at once | Combined with AND |
| ENR-22 | Search by student name/email | Matches |
| ENR-23 | `confirmSuspend` → cancel (`cancelAction`) | Nothing changed |
| ENR-24 | `confirmSuspend` → `suspend` | Status → suspended; flash |
| ENR-25 | Suspended student opens the player | **Access denied** |
| ENR-26 | `reinstate` | Status → active; access restored |
| ENR-27 | `confirmRevoke` → `revoke` | Status changes; access gone |
| ENR-28 | Revoked student's existing in-progress attempt | Confirm the rule; must not silently keep granting content |
| ENR-29 | Revoke, then the student uses a media URL minted 30s earlier | **Denied** — see RBAC-X12 |
| ENR-30 | Suspend an **already suspended** enrollment | No-op or clear refusal; no duplicate audit entry |
| ENR-31 | Reinstate an **active** enrollment | No-op |
| ENR-32 | Tamper `enrollmentId` to another admin's scope / bad id | Rejected |
| ENR-33 | Every state change in the audit log | Actor, action, enrollment id |
| ENR-34 | `actingOn()` modal shows the **correct** enrollment's details | Right student, right course — no off-by-one |
| ENR-35 | Open the confirm modal, then delete the record elsewhere, then confirm | Handled, no 500 |
| ENR-36 | 50 enrollments, paginate + filter | Correct |

---

## §10 — SETTINGS (SET)

| ID | Check | Expected |
|---|---|---|
| SET-01 | `/admin/settings` renders grouped | `branding` and `learning` groups, ordered |
| SET-02 | Eight seeded keys present | organisation_name, support_email, logo_path, mail_from_address, mail_from_name, email_footer, video_completion_threshold, default_passing_percentage |
| SET-03 | Change `branding.organisation_name` → save | Flash; value persists |
| SET-04 | Reload **any** page | New org name in sidebar, header, page titles — **nothing hardcoded** (rule S-1) |
| SET-05 | Check an email in `/dev/mail` | New org name in the email too |
| SET-06 | `branding.organisation_name` left empty | Rejected (`required`) |
| SET-07 | `branding.support_email` = `not-an-email` | Confirm validation exists; log if a bad address is accepted |
| SET-08 | Integer setting `video_completion_threshold` = `abc` | Rejected (`numeric`) |
| SET-09 | `video_completion_threshold` = `-10` | Confirm a floor exists; log if a negative saves |
| SET-10 | `video_completion_threshold` = `150` | Confirm a 0–100 cap exists; log if not |
| SET-11 | `default_passing_percentage` = `60` → new assessment | New assessment defaults to 60 |
| SET-12 | Non-required string setting cleared | Accepted (nullable branch) |
| SET-13 | Settings cache flushed on save | Change visible **immediately**, not after a manual `cache:clear` |
| SET-14 | `is_public` settings vs private | Private values (mail_from_address) never leak to a guest-rendered page |
| SET-15 | Every setting change in the audit log | Key, old value, new value |
| SET-16 | XSS in `organisation_name` | Escaped in layout **and** in emails |
| SET-17 | Very long org name (500 chars) | Layout does not break |
| SET-18 | Two admins save settings simultaneously | Last write wins cleanly |

---

## §11 — AUDIT LOG (AUD)

| ID | Check | Expected |
|---|---|---|
| AUD-01 | `/admin/audit-log` renders newest first | Yes |
| AUD-02 | `actionOptions()` populates the filter dropdown | From real recorded actions, not a hardcoded list |
| AUD-03 | `updatingActionFilter` for each action type | Correct subsets |
| AUD-04 | Search by actor name/email | Matches |
| AUD-05 | Every §5–§10 action produced an entry | Cross-check ~15 actions you performed |
| AUD-06 | Entry contains actor, action, target type+id, timestamp, IP | All present |
| AUD-07 | Timestamps in the correct timezone | Consistent with the rest of the app |
| AUD-08 | Log is **read-only** | No edit or delete affordance anywhere |
| AUD-09 | Attempt a delete via a crafted request | No route exists / rejected |
| AUD-10 | Sensitive values (passwords, tokens) in entries | **Never** logged in plaintext |
| AUD-11 | Media access entries are throttled | Not one row per byte-range request |
| AUD-12 | Pagination over 200+ entries | Fast, correct |
| AUD-13 | Filters survive refresh via query string | Yes |

---

## §12 — INSTRUCTOR AREA (I) — log in as `instructor@lms.test`

> **Prerequisite:** as admin, assign at least 2 courses to this instructor, and
> leave at least 1 course **unassigned** — the unassigned one is the control for
> every scoping test.

### 12.1 Instructor dashboard — `/instructor`

| ID | Check | Expected |
|---|---|---|
| I-01 | Loads with **zero** assigned courses | Empty state, no crash, no division by zero |
| I-02 | Loads with 2 assigned courses | KPIs reflect **only** those two |
| I-03 | Total students figure | Counts only students enrolled in assigned courses |
| I-04 | Enroll a student in the **unassigned** course | Instructor's count does **not** change |
| I-05 | Average progress / completion tiles | Match what §12.3 shows per course |
| I-06 | Pending-grading or attempts tile (if present) | Accurate against §12.5 |
| I-07 | Any recent-activity list | Only assigned-course activity |
| I-08 | Search the whole page for ₹ / price / revenue / order | **Zero hits** (FR-INS-10) |
| I-09 | Dashboard at 375px | Tiles stack cleanly |
| I-10 | All dashboard links resolve | No 404, no 403 |

### 12.2 My courses — `/instructor/courses`

| ID | Check | Expected |
|---|---|---|
| I-11 | Lists exactly the assigned courses | 2 — never the third |
| I-12 | Direct URL to the unassigned course id | 403 (RBAC-X01) |
| I-13 | Each card shows title, student count, progress | Accurate |
| I-14 | **No** create/edit/delete course affordance anywhere | FR-INS-08 |
| I-15 | Draft assigned course | Confirm whether it appears; log the rule |
| I-16 | Archived assigned course | Confirm and log |
| I-17 | Admin unassigns a course mid-session, instructor refreshes | Gone; direct URL now 403 |
| I-18 | Search/sort on the list (if present) | Works, stays scoped |

### 12.3 Course detail — `/instructor/courses/{course}`

| ID | Check | Expected |
|---|---|---|
| I-19 | Renders curriculum (modules + lessons) read-only | No edit controls |
| I-20 | Enrolled-students list | Only students enrolled in **this** course |
| I-21 | Per-student progress % | Matches the student's own view in §13 |
| I-22 | Course with **zero** enrolled students | Empty state |
| I-23 | Suspended enrollment in the list | Shown with its status, or excluded — confirm and log |
| I-24 | Student progress figures after the student completes a lesson | Update on refresh |
| I-25 | Assessment list for this course | Present, links into the builder |
| I-26 | No price/revenue anywhere on the page | Confirmed |
| I-27 | Links to each student's progress detail | Resolve correctly |
| I-28 | 30+ enrolled students | Paginates |

### 12.4 Student progress detail — `/instructor/courses/{course}/students/{enrollment}`

| ID | Check | Expected |
|---|---|---|
| I-29 | Renders per-lesson completion for that student | Accurate against the student's real progress |
| I-30 | Overall progress bar | Matches the percentage on the course detail page |
| I-31 | Assessment attempts for that student listed | Scores, status, timestamps |
| I-32 | Student with **zero** progress | 0%, empty state, no crash |
| I-33 | Student with 100% progress | Shows completed state |
| I-34 | Mismatched `{course}`/`{enrollment}` pair | 403/404 (RBAC-X05) |
| I-35 | Another instructor's enrollment id | 403 |
| I-36 | Video-lesson completion honours the `video_completion_threshold` setting | Change it in §10 and re-verify |
| I-37 | No financial data | Confirmed |

### 12.5 Instructor assessments and results

| ID | Check | Expected |
|---|---|---|
| I-38 | `/instructor/assessments` lists only assigned-course assessments | Yes |
| I-39 | Open the builder from here | Full authoring works (same component as admin) |
| I-40 | Instructor **creates** a question, publishes the assessment | Allowed on their own course |
| I-41 | `/instructor/assessments/{id}/results` with zero attempts | Empty state, statistics show zeros not NaN |
| I-42 | Results after 3 student attempts | All 3 listed with score, status, submitted-at |
| I-43 | `AssessmentStatisticsService` figures: average, pass rate, high/low | Verify by hand against the 3 attempts |
| I-44 | Pass rate with `passing_percentage` changed | Recalculates consistently |
| I-45 | Per-question statistics (if shown) | Correct counts |
| I-46 | Results pagination over 25+ attempts | Works |
| I-47 | Results for another instructor's assessment | 403 |
| I-48 | The **admin** view of the same results URL | Same numbers, admin chrome |
| I-49 | An `in_progress` attempt in the results list | Handled — not counted as a score of 0 in the average |
| I-50 | An `expired` / `abandoned` attempt | Displayed distinctly |

---

## §13 — STUDENT AREA (S) — the data source for §12

> Run these as `student@lms.test` **after** granting an enrollment in §9.
> Their purpose is twofold: verify the student surface, and generate the
> progress and attempt data that §12 asserts against.

| ID | Check | Expected |
|---|---|---|
| S-01 | `/dashboard` before any enrollment | Empty state pointing at the catalogue |
| S-02 | `/dashboard` after the grant | Course card with 0% progress and a "continue" CTA |
| S-03 | `/my-courses` | Lists granted courses only |
| S-04 | `/learn/{course}` with **no** lesson segment | Resumes at the first/last-viewed lesson |
| S-05 | `/learn/{course}/{lesson}` for a lesson in the course | Opens that lesson |
| S-06 | `/learn/{course}/{lesson}` for a lesson from a **different** course | **404** — not in this course (not a 403) |
| S-07 | `/learn/{unenrolled course}` | 403 via `EnrollmentAccessService` |
| S-08 | Video lesson plays; seek forward | Range requests work |
| S-09 | Watch past the completion threshold | Lesson marks complete; progress bar advances |
| S-10 | Watch **below** the threshold | Not marked complete |
| S-11 | Text lesson: mark complete | Progress advances |
| S-12 | Document/presentation lesson | Renders through the protected media route only |
| S-13 | Resource lesson: download | `Content-Disposition: attachment` |
| S-14 | Complete every lesson | Course progress reaches 100% |
| S-15 | Quiz lesson → start attempt | Runner opens |
| S-16 | Answer each of the 4 question types | All recordable |
| S-17 | Submit the attempt | Graded; score matches marks × correct answers, minus negatives |
| S-18 | 🔴 Open the **same** assessment in a second tab while one attempt is `in_progress` | Resumes the same attempt — **never** creates a second. This is the partial unique index (FR-ASMT-16, AC-26) |
| S-19 | Force a second `in_progress` row via tinker | Database throws — index proven |
| S-20 | Time-limited assessment: let the clock run out | Attempt expires; cannot submit afterwards |
| S-21 | `max_attempts = 2`: try a third | Refused |
| S-22 | `max_attempts` blank: take 5 attempts | All allowed |
| S-23 | `scoring_policy = highest` with 3 attempts | Recorded score is the highest |
| S-24 | `scoring_policy = latest` | The most recent |
| S-25 | `scoring_policy = first` | The first |
| S-26 | `/attempts/{ulid}/result` | Score, pass/fail, reveal honours `answer_reveal` |
| S-27 | `/assessments/{id}/history` | All attempts listed |
| S-28 | Negative marking applied | Score reduces correctly; confirm it cannot go below the defined floor |
| S-29 | Submit an attempt with **no** answers | Scores 0, no crash |
| S-30 | Refresh mid-attempt | Answers preserved |
| S-31 | Browser Back mid-attempt | No duplicate submission |
| S-32 | Double-click Submit | One submission only |
| S-33 | After all this, re-run §12.3 and §12.5 | Instructor's figures now match exactly what the student did |

---

## §14 — SHARED COMPONENT LIBRARY (C)

> 15 components in `resources/views/components/`. Test each **in situ** on the
> pages above — a component that only works on one screen is the drift Phase 15
> exists to remove.

| ID | Component | Checks |
|---|---|---|
| C-01 | `x-button` | Every variant renders; disabled state not clickable; loading state during a Livewire action; consistent height across all admin pages |
| C-02 | `x-card` | Consistent padding/shadow on dashboard, detail pages, forms |
| C-03 | `x-input` | Label bound to input (`for`/`id`); error state red + message; disabled; placeholder; `aria-invalid` when errored |
| C-04 | `x-select` | Options render; selected value persists after a validation failure; empty-options case |
| C-05 | `x-textarea` | Rows, max-length feedback, error state |
| C-06 | `x-checkbox` | Toggles; label clickable; keyboard Space works |
| C-07 | `x-table` | Header, sort affordances, zebra/hover, empty case, horizontal scroll at 375px |
| C-08 | `x-modal` | Opens/closes; Escape closes; focus trapped inside; focus returns to trigger on close; backdrop click behaviour consistent everywhere |
| C-09 | `x-alert` | All variants (success/error/warning/info); dismiss works; `role="alert"` |
| C-10 | `x-badge` | One variant per status enum; readable contrast in every colour |
| C-11 | `x-empty-state` | Icon + message + CTA; appears on **every** empty list you found above |
| C-12 | `x-pagination` | Prev/next disabled at the boundaries; current page marked; works with filters |
| C-13 | `x-breadcrumbs` | Last crumb unlinked; parents navigate; correct on every nested page |
| C-14 | `x-progress-bar` | 0%, 50%, 100% render correctly; has an accessible value; never overflows its track |
| C-15 | `x-stat-tile` | Label + value + optional delta; handles a 0 value and a very long label |
| C-16 | **Consistency sweep** | Screenshot every admin page. Button heights, card padding, table density and heading scale must be identical across all of them. Any drift is a Phase 15 defect — log it now |
| C-17 | **No forks** | `grep` the views for any bespoke `<button class=` or hand-rolled table that should be using the component. Each hit is a defect |

---

## §15 — SECURITY, ERRORS AND EDGE CASES (SEC)

| ID | Check | Expected |
|---|---|---|
| SEC-01 | Every destructive action is POST/Livewire, never a GET link | No `GET` route mutates state |
| SEC-02 | Forms carry CSRF | Stripping the token fails the request |
| SEC-03 | Mass assignment: add `role=super_admin` to a student-form POST | Ignored (NFR-SEC-07) |
| SEC-04 | Mass assignment: add `status=active` to bypass activation | Ignored |
| SEC-05 | Mass assignment: `price_amount` direct on a course POST | Ignored — price flows through `Money` |
| SEC-06 | SQL injection in every search box (§5, §6, §7, §9, §11) | Parameterised, no error, no data leak |
| SEC-07 | XSS payload in every free-text field, then view it on every surface that displays it | Always escaped |
| SEC-08 | Stored XSS via instructor bio rendered on a **public** course page | Escaped |
| SEC-09 | Error pages in `APP_DEBUG=false` | No stack trace, no file path, no env value |
| SEC-10 | 500 handler | Styled page, incident logged |
| SEC-11 | `/dev/mail` with `LMS_MAIL_PREVIEW_ENABLED=false` | 404 |
| SEC-12 | `/dev/mail` in production config | 404 even with a cached route table |
| SEC-13 | Password fields | `type=password`, excluded from autofill logs, never echoed back on a validation error |
| SEC-14 | Security headers on an authenticated page | Check `X-Frame-Options`/CSP, `X-Content-Type-Options` |
| SEC-15 | Session cookie flags | `HttpOnly`, `SameSite`; `Secure` in production |
| SEC-16 | Concurrent identical Livewire actions (double-click every destructive button in this document) | Never a double effect |
| SEC-17 | Very large paste (100k chars) into a textarea | Rejected by `max:`, not a 500 |
| SEC-18 | Emoji and RTL text in names | Stored and rendered correctly |
| SEC-19 | Null-byte in a search string | Handled |
| SEC-20 | Directly guess a sequential id on every `{id}` route as the wrong role | 403 every time — no information leaked in the error |

---

## §16 — RESPONSIVE, ACCESSIBILITY AND POLISH (UX)

| ID | Check | Expected |
|---|---|---|
| UX-01 | Every page at 375 / 768 / 1024 / 1440 px | No horizontal scroll, no clipped text, no overlapping controls |
| UX-02 | Keyboard-only traversal of one full admin flow (create student → suspend → delete) | Completable without a mouse |
| UX-03 | Focus visible on every interactive element | Yes |
| UX-04 | Tab order matches visual order on every form | Yes |
| UX-05 | Every form input has an associated label | Inspect the DOM, not just the visuals |
| UX-06 | Errors announced to assistive tech | `aria-describedby` / `role="alert"` |
| UX-07 | Colour contrast on badges, muted text and disabled buttons | Meets AA |
| UX-08 | Status conveyed by more than colour alone | Text label present too |
| UX-09 | Loading states on every Livewire action | `wire:loading` feedback, no dead-looking UI |
| UX-10 | Long content (200-char course title) on every surface | Truncates gracefully |
| UX-11 | Empty states everywhere a list can be empty | All present (cross-check against every empty case in this document) |
| UX-12 | Browser zoom to 200% | Still usable |
| UX-13 | Print an admin table | Legible |
| UX-14 | Dark mode / reduced motion (if supported) | Consistent |

---

## §17 — AUTOMATED GATES (must be green alongside all of the above)

```bash
composer lint       # Pint
composer analyse    # Larastan level 8 — zero errors, no baseline
composer test       # Pest, whole suite, against real PostgreSQL
composer check      # all three
```

| ID | Check | Expected |
|---|---|---|
| GATE-01 | `composer check` on the branch | All three green before any push |
| GATE-02 | Test suite runs on PostgreSQL, not SQLite | Partial unique index is actually exercised |
| GATE-03 | Any bug found above has a regression test added | Rule 25 |

---

## BUG LOG

| # | ID | Severity | Page/Component | Steps | Expected | Actual |
|---|---|---|---|---|---|---|
| 1 | | | | | | |
| 2 | | | | | | |
| 3 | | | | | | |

**Severity:** S1 security/data-loss · S2 blocks a core flow · S3 wrong behaviour with a workaround · S4 cosmetic

---

## COVERAGE SUMMARY

| Section | Cases |
|---|---|
| §0 Setup | 7 |
| §1 Auth | 24 |
| §2 RBAC | 55 |
| §3 Shell | 17 |
| §4 Dashboard | 14 |
| §5 Students | 60 |
| §6 Instructors | 27 |
| §7 Courses / Modules / Lessons / Media | 75 |
| §8 Assessments & Questions | 83 |
| §9 Enrollments | 36 |
| §10 Settings | 18 |
| §11 Audit log | 13 |
| §12 Instructor area | 50 |
| §13 Student area | 33 |
| §14 Components | 17 |
| §15 Security | 20 |
| §16 UX | 14 |
| §17 Gates | 3 |
| **Total** | **≈566** |

### If you only have one hour, run these 12

`RBAC-I04` · `RBAC-I13` · `RBAC-X05` · `RBAC-X12` · `Q-38` · `Q-39` · `S-18` ·
`CRS-32` · `CRS-48` · `ENR-10` · `MED-13` · `SEC-03`

They are the ones where a failure is a security or data-integrity defect rather
than a UI defect.
