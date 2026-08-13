# UI Guide — Maieutic LMS

**Every screen in this product is built against this document.**

It is derived from the reference prototypes in `sample ui/ui/` and the brand design system in
`sample ui/ui/_ds/`. Where this file and that design system disagree, **the design system wins** and
this file gets corrected.

Written to be followed by Claude Code as much as by a person — that is how the screens will
actually get built.

---

## 0. How to use this with Claude

Open any UI task with:

> Read `docs/UI-GUIDE.md` in full before writing anything.
> Then open the matching screen in `sample ui/ui/<Audience>.dc.html` and study the section for
> [screen name].
> Build [screen] in Blade + Livewire following the guide exactly. Reuse the components in
> `resources/views/components/`. Do not invent tokens, colours, or a second version of an existing
> component. Do not copy the reference's inline styles — use Tailwind utilities bound to our
> tokens.

Then check the result against §14 before opening a PR.

---

## 1. The brand

Maieutic is a **premium learning-experience company** — not a coaching institute, not a generic
EdTech product. Reference points: Apple, Linear, Notion, Stripe, Pentagram. Anti-references: Canva
templates, gradient SaaS dashboards, course marketplaces.

> *maieutic* (adj.) — of the Socratic method: drawing out ideas already latent in the mind.

The house style is **editorial**: big type, big air, tight alignment, one or two colours per screen.
A page should read like a well-set magazine spread, not a control panel.

**Restraint is the signal of quality.** When in doubt, remove something.

---

## 2. The reference files

| File | Contains |
|---|---|
| `sample ui/ui/Auth & States.dc.html` | Login, forgot, reset, activate, verify, change password, suspended, 404, 403, 500 |
| `sample ui/ui/Student.dc.html` | Dashboard, my courses, browse, detail, checkout, success, player, quizzes, quiz, result, progress, profile |
| `sample ui/ui/Instructor.dc.html` | Dashboard, courses, assessments, new assessment, results, progress, student detail, profile |
| `sample ui/ui/Super Admin.dc.html` | Dashboard, students, student detail, new student, instructors, courses, builder, quiz builder, enrolments, orders, settings |
| `sample ui/ui/_ds/` | The design system — tokens, brand rules, component inventory |

**41 screens across four audiences.** Between them they answer nearly every layout question you
will have. Read the relevant one before designing anything from scratch.

### How to read them

They are **Claude Design prototypes**, not production code:

- `<sc-if value="{{ isLogin }}">` and `<sc-for list="{{ items }}">` are prototype directives. In our
  stack these become `@if` / `@foreach` in Blade.
- **Everything is inline-styled.** Correct for a mockup, wrong for us. Translate to Tailwind
  utilities bound to the tokens in §5.
- `_ds_bundle.js` exposes React components at `window.Maieutic.*`. **We do not consume it.** We take
  the tokens and the rules, not the code.
- The dark strip of buttons at the top of each file is a **prototype screen switcher**, not product
  UI. Never build it.

### The reference is the floor, not the ceiling

You were asked for something **better** than the reference: more polished, more interactive, more
responsive, production-quality. Match its discipline — type contrast, whitespace, hairline borders —
then exceed it where it is thin:

| The reference shows | We must also deliver |
|---|---|
| Finished screens only | Real loading skeletons, empty and error states (§11) |
| Desktop widths | Full responsive behaviour, 360 px → 1920 px |
| Static markup | Real keyboard operability, focus management, ARIA |
| Happy-path data | Pagination, long-text truncation, zero/one/many cases |
| — | Reduced-motion support, WCAG 2.1 AA contrast |

Exceeding the reference means **more rigour, not more decoration.** Do not add gradients, glows,
illustrations or animation flourishes it does not have.

---

## 3. Screens mapped to phases, and who builds them

**Decided 2026-08-13, after Phase 4 merged.** UI is no longer built by one person. It splits into a
shared layer and feature screens, and feature screens follow the phase.

### The shared layer — one owner, permanently

| Layer | Owner | Files |
|---|---|---|
| Design foundation — tokens, fonts, base layer | **Govind** (done, merged) | `resources/css/app.css`, `vite.config.js` |
| Shared component library | **Srivathsa** | `resources/views/components/**` |
| Layouts and shells | **Srivathsa** | `resources/views/layouts/**` |

The twelve components and the four shells stay single-owner. That is what stops a second button
component existing, and it is not negotiable however convenient a local copy looks.

### Feature screens — owned by subdirectory

Subdirectory ownership is what makes parallel UI work safe. It is not a formality: four branches
merged in one week with **zero overlapping files** because of it. Stay in your own directory.

| Screens | Livewire | Views | Owner |
|---|---|---|---|
| Course Builder, course list | `Admin/Courses/**` | `livewire/admin/courses/**` | **Srivathsa** |
| Public catalogue, course detail | `Catalogue/**` | `livewire/catalogue/**` | **Srivathsa** |
| Admin enrolments — table, grant, revoke | `Admin/Enrollments/**` | `livewire/admin/enrollments/**` | **Shashank** |
| Assessment, instructor | `Assessment/**`, `Instructor/**` | matching | **Srivathsa** |
| Reporting, mail templates | `Reporting/**` | `livewire/reporting/**` | **Shashank** |
| Student dashboard, my courses, player | `Student/**` | `livewire/student/**` | **Govind** |

### Screen-to-phase map

| Reference screens | Phase | Backend | UI |
|---|---|---|---|
| Auth & States — all 10 | 2 | Govind | ✅ done |
| Super Admin — dashboard, students, instructors, settings | 4 | Srivathsa | ✅ done |
| Super Admin — courses, builder · public browse + detail | 5 | Govind ✅ | **Srivathsa** |
| Super Admin — enrolments | 6 | Govind ✅ | **Shashank** |
| Student — dashboard, my courses, player | 7 | Govind | Govind |
| Student/Instructor — quizzes, quiz, result, assessments | 8 | Srivathsa | Srivathsa |
| Student/Instructor — progress | 9 | Govind | Govind |
| Instructor — all | 10 | Srivathsa | Srivathsa |
| Student — checkout, success · Super Admin — orders | 12 | Govind | Govind |

### Two things to know before starting a screen

**The backend defends itself.** A UI mistake cannot create a security hole. `GrantEnrollment` is the
only writer of enrollments, `RevokeEnrollment` throws without a reason, and every route authorises
server-side. Rendering a control never implies permission (Rule 20) — so build the screen you think
is right, and the action behind it will still refuse what it should.

**Phase 6's UI is the gentlest starting point** if you have not written Livewire on this project: a
table, a form and a confirm modal, all consuming actions that are already tested. It is also the
screen that grants paid access, so the confirm step matters — revocation asks for a reason because
the action demands one, not as a courtesy.

---

## 4. The rules nobody may break

1. **Never invent a colour.** Every colour comes from §5. No hex in a Blade file, ever.
2. **Never fork a component.** A second button component is a defect. Extend the existing one.
3. **No arbitrary Tailwind values** — `p-[13px]`, `text-[#00615c]`, `w-[347px]` are rejected. A
   value needed twice is a token.
4. **No emoji. Anywhere.** Not in UI, not in copy, not in commits.
5. **Focus is always visible.** Never remove the ring.
6. **Light mode only in V1.** Do not build a dark theme. Dark teal *panels* are a design device, not
   a theme.
7. **Hiding a control is never security.** Every screen sits behind a real policy check.
8. **Sentence case everywhere.** UPPERCASE is only for mono eyebrows.

---

## 5. Design tokens

Tailwind 4 is configured CSS-first — no `tailwind.config.js`. These replace the placeholder blue
palette currently in `resources/css/app.css`.

```css
@theme {
    /* ---- Typography ------------------------------------------------ */
    --font-serif: 'Newsreader', Georgia, 'Times New Roman', serif;
    --font-sans:  'Hanken Grotesk', system-ui, -apple-system, 'Segoe UI', sans-serif;
    --font-mono:  'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;

    /* ---- Teal — primary brand, and the ONLY CTA colour ------------- */
    --color-teal-50:  #eaf2f1;
    --color-teal-100: #cde3e1;
    --color-teal-200: #a5ccc9;
    --color-teal-300: #6fada8;
    --color-teal-400: #3c8e88;
    --color-teal-500: #167871;
    --color-teal-600: #00615c;  /* Training Day Teal — base */
    --color-teal-700: #024e4a;
    --color-teal-800: #043b38;
    --color-teal-900: #052826;  /* sidebars, brand panels */

    /* ---- Red Sea — a RARE accent, not a second brand colour -------- */
    --color-red-50:  #fbeae9;
    --color-red-100: #f5cbc9;
    --color-red-200: #e79a96;
    --color-red-300: #d46861;
    --color-red-400: #b93a32;
    --color-red-500: #a31009;
    --color-red-600: #800d07;  /* Red Sea — base */
    --color-red-700: #630a05;
    --color-red-800: #470704;

    /* ---- Warm neutrals: paper → ink. NOT grey. --------------------- */
    --color-neutral-0:   #ffffff;
    --color-neutral-50:  #faf9f6;  /* paper — page background */
    --color-neutral-100: #f2f1ec;
    --color-neutral-200: #e5e3dc;  /* the hairline border workhorse */
    --color-neutral-300: #d2cfc6;
    --color-neutral-400: #b0aca1;
    --color-neutral-500: #8a867b;
    --color-neutral-600: #66625a;
    --color-neutral-700: #4a473f;
    --color-neutral-800: #2e2c27;  /* body text */
    --color-neutral-900: #1a1815;  /* ink — headings */

    /* ---- Accents. Two or three per screen, maximum. ---------------- */
    --color-melon:     #f2aa84;
    --color-lemon:     #e8d94e;
    --color-honeydew:  #008680;
    --color-marigold:  #fef1de;
    --color-rose:      #f8847e;

    /* ---- Radius --------------------------------------------------- */
    --radius-xs:   4px;
    --radius-sm:   8px;
    --radius-md:   12px;
    --radius-card: 16px;
    --radius-xl:   24px;

    /* ---- Shadows: soft, warm-tinted, minimal ---------------------- */
    --shadow-xs: 0 1px 2px rgba(26,24,21,0.05);
    --shadow-sm: 0 1px 3px rgba(26,24,21,0.06), 0 1px 2px rgba(26,24,21,0.04);
    --shadow-md: 0 6px 20px -6px rgba(26,24,21,0.10);
    --shadow-lg: 0 16px 48px -12px rgba(26,24,21,0.14);

    /* ---- Motion --------------------------------------------------- */
    --ease-standard: cubic-bezier(0.22, 0.61, 0.36, 1);
    --ease-out:      cubic-bezier(0.16, 1, 0.3, 1);
}
```

### Use the role, not the raw scale

| Role | Token |
|---|---|
| Page background | `neutral-50` — warm paper, never pure white |
| Card surface | `neutral-0` |
| Sunken / muted panel | `neutral-100` |
| Heading text | `neutral-900` |
| Body text | `neutral-800` |
| Muted text, metadata | `neutral-500` |
| Border | `neutral-200` |
| Stronger divider | `neutral-300` |
| Primary CTA | `teal-600` — the **only** CTA colour |
| CTA hover / pressed | `teal-700` / `teal-800` |
| Dark shells and panels | `teal-900` with white text |
| Brand-forward headings, eyebrows | `teal-600` |
| Success | `honeydew` |
| Warning | `melon` |
| Danger, destructive | `red-600` |

**Status colours are fixed.** Green means success, amber means warning, red means danger. Never
decorative — a red badge must mean something is wrong.

### Fonts — decided, all three are open source

| Family | Role | Licence |
|---|---|---|
| **Newsreader** | Serif display — headlines, pull quotes | SIL Open Font License 1.1 |
| **Hanken Grotesk** | Sans — UI and body | SIL Open Font License 1.1 |
| **JetBrains Mono** | Mono — eyebrows, labels, metadata | SIL Open Font License 1.1 |

**No licence to buy, no per-domain terms, no attribution in the UI.** The OFL permits commercial
use, embedding, self-hosting and modification. The only obligation is that the licence text
accompanies the font files if they are ever redistributed on their own — which we never do.

This closes the earlier question about Lucida Sans and Posterama from the brand guideline. Neither
is web-licensed, both would cost money per domain, and neither suits an editorial system. The three
above are the substitutes the design system chose, and they stand.

**Adding them is a `vite.config.js` change, not a `package.json` one.** Laravel's Vite plugin
downloads fonts at build time via Bunny Fonts and self-hosts the result — no npm dependency, no CDN
at runtime, no user IPs leaking to Google:

```js
fonts: [
    bunny('Newsreader',     { weights: [400, 500, 600] }),
    bunny('Hanken Grotesk', { weights: [400, 500, 600, 700] }),
    bunny('JetBrains Mono', { weights: [400, 500] }),
],
```

Instrument Sans is wired exactly this way today, so the pattern is already proven in this repo.

> **Weights are deliberately narrow.** Every extra weight is another file on the critical path. Add
> one only when a design genuinely needs it — not speculatively.

### Logo — both official variants exist, use them as supplied

The company supplies two official logos. They are in `docs/company logo/`. **Both are transparent
PNGs**, verified.

| Variant | File | Size | Use on |
|---|---|---|---|
| **Red / teal** — the M mark in Red Sea and teal, "aieutic" in red | `image.png` | 205 × 101 | White and paper surfaces |
| **White** — reversed, with tagline | `image (1).png` | 186 × 63 | `teal-900` panels and any dark surface |

**Where each one goes:**

| Surface | Variant |
|---|---|
| Login / auth brand panel (`teal-900`) | **White** |
| Admin sidebar (`teal-900`) | **White** |
| Instructor sidebar (`teal-900`) | **White** |
| Any dark teal section or footer | **White** |
| Login form panel, public pages, catalogue | **Red / teal** |
| Email templates, invoices, certificates | **Red / teal** |

#### Rules

1. **Never recolour, invert, redesign or reconstruct the logo.** No `filter: invert()`, no CSS
   recolouring, no redrawing the M in code. Two official variants exist — pick the right one.
2. **Never place either variant on a busy or patterned background**, per the brand guideline.
3. **Never stretch.** Constrain one dimension and let the other follow.
4. Keep clear space around it — at minimum the height of the M mark on every side.
5. Give it an accessible name: `alt="Maieutic"`. It is not decorative.

#### The size ceiling — read this before designing a hero

These are small raster files. On a 2× display they stay crisp only up to roughly **half their pixel
width**:

| Variant | Native | Safe max display width |
|---|---|---|
| Red / teal | 205 px | **~100 px** |
| White | 186 px | **~90 px** |

That is comfortably enough for a sidebar wordmark, a top bar, or an email header. **It is not enough
for a large hero lockup, a certificate, or print.** Do not design a layout that needs the logo
bigger than that until vector artwork exists.

For anything larger, keep the reference's approach: set **Maieutic** as live serif text
(`--font-serif`, 600, `-0.015em`). It scales perfectly, it is already the brand typeface, and it is
what the prototypes do on the auth panel today.

#### Two things worth knowing

- **The two variants are different lockups, not a colour swap.** The white one carries a tagline the
  red one does not, and their aspect ratios differ (2.95:1 vs 2.03:1). Do not assume a shared
  bounding box — size each one for its own placement.
- **The filenames need fixing when these move into the app.** `image (1).png` has a space and
  parentheses, which are hostile in URLs and build pipelines. At foundation time they become:

  ```
  resources/images/logo-maieutic.png          ← red / teal
  resources/images/logo-maieutic-reversed.png ← white
  ```

---

## 6. Typography

The signature of this brand is **the contrast between large serif headlines and small tracked mono
eyebrows.** A screen without that contrast is off-brand no matter how correct the colours are.

| Use | Family | Size | Weight | Tracking |
|---|---|---|---|---|
| Hero / brand panel | serif | 38–48px | 600 | `-0.015em` |
| Page title | serif | 28–38px | 600 | `-0.015em` |
| Section heading | serif | 20–24px | 600 | `-0.015em` |
| Card heading | serif | 18–19px | 600 | normal |
| Body | sans | 15–16px | 400 | normal |
| Small / secondary | sans | 13–14px | 400 | normal |
| **Eyebrow / kicker** | **mono, UPPERCASE** | 11–12px | 500 | `0.16em` |
| Metadata, counts, IDs | mono | 11–13px | 400 | `0.04em` |

Element defaults are already set by the design system: `h1`–`h3` are serif, semibold, `-0.015em`,
`text-wrap: balance`. `h4`–`h6` are sans. Do not re-declare them per screen.

- **Line height** 1.5–1.65 body, 1.2 headings, 1.05–1.18 display.
- **Measure** cap body at `68ch`. Unbroken long lines are the most common first-draft failure.
- **Body size** 16px on reading surfaces (public pages, player, course detail); 15px is acceptable
  in dense admin tables. Never below 13px for anything a user must read.
- **Casing** "Course catalogue", never "Course Catalogue". UPPERCASE only for 1–4 word eyebrows.

---

## 7. Layout and shells

Four shells cover every screen. Each is in the reference — read it before building.

### Auth — split screen

Left: `teal-900` brand panel with the M motif bottom-right, serif wordmark top, editorial headline
and mono eyebrow centre, mono footer. Right: white form panel, form column **400px**, vertically
centred, `fade-in 300ms var(--ease-out)` on mount.

Grid `1fr 1.15fr` — the form side is slightly wider. **Collapses to form-only below 1024px**; the
brand panel is decorative and must not push the form off-screen on mobile.

### Admin / instructor — dark sidebar

Sticky `aside`, **248px**, `teal-900`, full viewport height, white text. Serif wordmark at top with a
`rgba(255,255,255,0.12)` hairline beneath. Nav items are sans; the active item gets a lighter
surface, not a colour flip.

Below 1024px the sidebar becomes a drawer with a hamburger trigger. It must trap focus when open and
close on `Escape`.

### Student — content-first

Lighter chrome than admin. Top bar with avatar and notifications, paper background, cards on white.
The **player** is the exception: it needs a focused, low-chrome layout that keeps the video and the
lesson list visible together, collapsing to stacked below 1024px.

### Public — editorial

Marketing rhythm: `--content-max: 1240px`, 24px gutters, ~96px vertical section cadence, generous
air. This is where the brand is most visible — the catalogue and course detail pages are the first
thing a prospective buyer sees.

### Spacing

4px base: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80, 96, 128. Page inset ≥ 32px. **Left-align text by
default** — centred body copy is a brand violation; centring is for short hero statements only.

### Responsive breakpoints

Every screen is verified at **360 / 768 / 1024 / 1440 / 1920**. The reference only shows desktop —
mobile behaviour is ours to design, and it is not optional.

---

## 8. Components

Twelve components exist in `resources/views/components/`:

```
alert  badge  button  card  checkbox  empty-state
input  modal  pagination  select  table  textarea
```

**Need a variant? Add a prop.** Do not create `button-2`, `primary-button`, or a local `<div>` that
reimplements one. A genuinely new primitive is a conversation with the component-library owner, not
a commit.

**Buttons** — Primary: solid `teal-600`, white text, **one per screen**. Secondary: white surface,
`neutral-200` border, ink text. Ghost: text only. Destructive: `red-600`, and always confirms first.
Radius 8–12px. Height 36–40px. **Never pill-shaped** — pills are for badges only.

**Cards** — white on paper, 1px `neutral-200` hairline, 16px radius. **Hierarchy comes from borders
and spacing, not shadow.** Resting cards have no shadow or `shadow-xs`; soft shadow on hover only.
No coloured left-border accent cards.

**Inputs / selects** — 36–40px height, 8px radius, 1px `neutral-200` border, white surface, 13–14px
sans. Focus shows the teal ring. Every input has a real `<label>` — placeholders are not labels.

**Status pills** — `radius-full`, 11–12px, soft tint background with a darker text of the same
family (`teal-50`/`teal-700`, `red-50`/`red-600`). Never a saturated fill.

**Avatars** — circle, 34px in bars, initials in 13px semibold, `teal-600` on white or white on
`teal-600`. Notification dot: 7px `red-600` circle with a 1.5px white ring.

**Tables** — hairline row separators, no zebra striping, no vertical rules. Column headers are small
mono uppercase, tracked. Numeric columns right-aligned. Long lists paginate — never render 500 rows.

**Tabs / filters** — quiet by default; the active tab gets weight and a teal underline or a subtle
surface, never a heavy filled block.

---

## 9. The "M" angle motif

The defining decorative element: the triangular geometry of the Maieutic "M", rendered as a **single
diagonal angle** in two palette colours at the edge or corner of a panel. As implemented in the
reference:

```css
background:
  linear-gradient(315deg, var(--color-teal-600) 0 26%, transparent 26.5%),
  linear-gradient(315deg, var(--color-red-600)  0 34%, transparent 34.5%);
```

**Use sparingly** — a brand panel corner, an occasional callout. Never a repeating pattern, never a
background texture, never more than one per screen.

---

## 10. Backgrounds and imagery

Flat warm paper or white. **No aggressive gradients** — the M motif is the only gradient in the
system. Occasional soft tint blocks (`marigold`, `teal-50`) separate sections. Dark sections use
`teal-900` with white text. No noise, no textures, no photographic hero wallpaper.

Imagery, where used: authentic and natural — real people learning and collaborating, hands, devices,
lines of connection. Faces often not required. **Avoid** fake corporate smiles, graduation caps,
staged classrooms, stock clichés. Until real assets exist, use labelled placeholders — never
lorem-ipsum imagery or an unlicensed stock photo.

---

## 11. States — all five, every time

A screen is not done when the happy path renders.

| State | Requirement |
|---|---|
| **Loading** | Skeletons matching the final layout. No layout shift. Spinners are not the primary device. |
| **Empty** | `<x-empty-state>`: say what would be here and give the action that creates it. Never a bare "No results". |
| **Error** | Say what failed and what to do next. Never a stack trace or raw exception. |
| **Partial** | Paginate. Truncate long text with a title attribute. Handle zero / one / many. |
| **Success** | Consistent flash or toast after create, update and destructive actions. |

**Empty states matter most here** — the entire product is empty on day one, so they are the first
thing a real user sees.

The reference has dedicated screens for suspended accounts, 404, 403 and 500. Build all four; do not
let framework defaults ship.

---

## 12. Interaction, motion, accessibility

**Hover** — buttons darken one step (`teal-600` → `teal-700`). Cards raise slightly: border darkens,
subtle shadow, ~1px lift. Links go to `teal-700` with `0.18em` underline offset. **No colour-flip
surprises.**

**Press** — one step darker (`teal-800`), optionally `scale(0.98)`. Subtle, tactile, never a squish.

**Focus** — visible 3px teal ring on `:focus-visible`, set globally. **Never remove it.**

**Motion** — 120–320ms. `--ease-standard` for most, `--ease-out` for entrances. Fades and 2–8px
translations only. **No bounces, no springs, no parallax.** `prefers-reduced-motion` is respected in
the base layer — do not bypass it with inline animation.

**Accessibility is WCAG 2.1 AA and is not negotiable** (NFR-UX-03). Build it in — retrofitting in
Phase 15 costs far more:

- Semantic HTML. A clickable `div` is a bug. `<button>` for actions, `<a>` for navigation.
- Every input has a real label. Placeholders are not labels.
- Contrast ≥ 4.5:1 body, ≥ 3:1 large text. Check `neutral-500` on `neutral-50` before using it for
  anything that must be read.
- Full keyboard operability, logical tab order. Modals and drawers trap focus and close on `Escape`.
- Icons `aria-hidden` unless they are the only content, in which case they need an accessible name.
- **Never convey meaning by colour alone** — a red border needs text beside it.
- The video player and quiz runner must be fully keyboard-operable. They are the two hardest
  surfaces; design for it from the start.

**Icons** — Lucide, stroke only, 1.5–2px, sized to text (18–20px inline, 16px dense). `currentColor`,
never multicolour. Icons support text, they do not replace it. **No emoji, no unicode glyph icons.**

---

## 13. Voice and copy

Copy is part of the design. Bad copy makes good layout look cheap.

**Intelligent, calm, precise — a thoughtful mentor, never a hype marketer.** Address the learner as
"you"; "we" sparingly. Active voice, concrete verbs — *understand, question, build, master* — never
*empower, unlock, revolutionise*.

One clear message per screen, one primary action. If a word can be cut and the point survives, cut
it.

| Good | Bad |
|---|---|
| "Sign in" · "Welcome back — pick up where you left off." | "Login to Your Account!" |
| "Answers fade. The questions stay." | "World's #1 Learning Platform!!!" |
| "3 questions · 92% complete" | "You're doing amazing!!" |
| "This course has no lessons yet." | "Oops! Nothing here 😢" |
| "Begin a session" · "Explore programs" | "Unlock Your Potential Today! 🚀" |

Numerals for data. Em dashes for asides. Minimal exclamation marks. **No emoji, ever.**

---

## 14. Before you open a UI PR

- [ ] No hex codes, no arbitrary Tailwind values, no invented tokens
- [ ] No component duplicating an existing one
- [ ] Loading, empty and error states all present
- [ ] Keyboard: full tab order, visible focus, `Escape` closes overlays
- [ ] Contrast checked on the real token pairs used
- [ ] Verified at 360 / 768 / 1024 / 1440 / 1920
- [ ] Type contrast present — serif headings against mono eyebrows
- [ ] Sentence case, no emoji, copy passes §13
- [ ] No inline styles copied from the reference prototypes
- [ ] `composer check` green — Pint, Larastan level 8, Pest
- [ ] Screenshots in the PR description, desktop **and** mobile

---

## 15. Open questions — ask, do not guess

1. ~~**Font licensing.**~~ **Resolved 2026-08-13** — Newsreader, Hanken Grotesk and JetBrains Mono,
   all SIL Open Font License 1.1, self-hosted through the Vite font pipeline. See §5. The brand
   guideline's Lucida Sans and Posterama are not web-licensed and are not being pursued.
2. ~~**Logo.**~~ **Resolved 2026-08-13** — both official variants supplied and verified transparent:
   red/teal for light surfaces, white for dark. No reversed lockup needs commissioning. See §5.
   One residual, not blocking: they are small rasters (205 px and 186 px wide), so vector artwork is
   still wanted for certificates, invoices and print. Live serif text covers large display use until
   then.
3. **Icons.** Lucide assumed. Needs self-hosting, and `package.json` is Track C's file.
4. **Where `sample ui/` lives.** ~300KB of reference HTML plus a React bundle we do not consume. It
   probably belongs in `docs/design/` rather than the application root, and may not belong in git at
   all.
5. ~~**UI ownership.**~~ **Resolved 2026-08-13** — split into a single-owner shared layer and
   per-subdirectory feature screens. Phase 5's UI is Srivathsa's, Phase 6's is Shashank's. See §3.

---

## 16. Implementation plan

### Step 0 — the gate ✅ CLEARED

- [x] Phase 4 merged to `main`
- [x] UI ownership decided — see §3
- [x] Design foundation merged (PR #7)
- [ ] **Phase 6 merged** — PR #9. The enrolment screens consume `GrantEnrollment` and
      `RevokeEnrollment`; until it lands, Shashank has nothing to build against.

### Step 1 — design foundation ✅ DONE, merged in PR #7

Already on `main`, so start from it rather than repeating it:

| Landed | Where |
|---|---|
| Brand tokens — teal, warm neutrals, radius, shadows, motion | `resources/css/app.css` |
| Three self-hosted fonts — Newsreader, Hanken Grotesk, JetBrains Mono | `vite.config.js` |
| Both official logos | `public/images/logo-maieutic{,-reversed}.png` |
| `eyebrow` and `m-motif` utilities | `resources/css/app.css` |
| Base element defaults — serif `h1`–`h3`, focus ring, reduced motion | `resources/css/app.css` |

The compatibility aliases in `app.css` (`brand-*`, `danger-*`, and the `zinc-*` shim) exist so
pre-existing views picked up the brand without being edited. **Write `teal-*` and `neutral-*` in new
work**; the shim is scheduled for deletion once the last `zinc-*` reference is gone.

### Step 2 — component library · the 12 primitives · PARTLY DONE

All twelve were restyled against the brand tokens in PR #7 and are on `main`:

```
alert  badge  button  card  checkbox  empty-state
input  modal  pagination  select  table  textarea
```

Plus `breadcrumbs` and `stat-tile` from Phase 4. **Use them. Do not fork them.** If one needs a
variant, add a prop — that is a conversation with Srivathsa, who owns the library, not a local copy.

**Still outstanding:** loading and skeleton states. The components carry default, hover,
focus-visible, disabled and error; a shared skeleton treatment does not exist yet, and every table
and card in Phases 5 and 6 will want one. Worth agreeing once rather than three times.

### Step 3 — shells · one done, three to build

| Shell | State |
|---|---|
| **Auth** — split screen, `teal-900` brand panel + 400 px form column | ✅ done, PR #7 |
| **Admin** — 248 px `teal-900` sidebar, drawer below 1024 px | Phase 4's exists; needs the brand pass |
| **Student** — content-first top bar | not built (Phase 7) |
| **Public** — editorial, 1240 px, 96 px rhythm | not built (Phase 5) |

Each shell handles its own responsive collapse, focus trapping and skip-to-content link **before**
any screen is built inside it. Retrofitting focus management into a finished shell is miserable —
the auth shell is the worked example to copy from.

### Step 4 — screens · WE ARE HERE

Parallel from now on. Each screen follows the §14 checklist and ships with loading, empty and error
states. Stay in your own subdirectory (§3).

| Who | Build | Blocked on |
|---|---|---|
| **Srivathsa** | Course Builder — module/lesson tree, drag reorder, publish checklist | nothing |
| **Srivathsa** | Public catalogue + course detail — metadata only, AC-01 | nothing; needs the public shell |
| **Shashank** | Admin enrolments — table, grant form, revoke modal | **PR #9 merging** |
| **Govind** | Student dashboard, my courses, player | Phase 7 |

**Two rules that matter more than the order:**

Drag-and-drop reorder must call the existing `ReorderModules` / `ReorderLessons` actions and send the
**complete** ordered id list. Both refuse a partial or foreign set — a stale page reloads rather than
corrupting order, which is deliberate. Do not "fix" that by relaxing the action.

The public catalogue renders **metadata only** — titles, durations, outcomes, price. No lesson body,
no media, no resource, by any URL (AC-01). The policies enforce it; the view must not be the only
thing standing between a guest and paid content.

### Step 5 — Phase 15

Unchanged, and still needed: cross-browser verification, full WCAG 2.1 AA audit, N+1 profiling,
responsive sweep at all five breakpoints, component consolidation.

**If Steps 1–4 are done properly, Phase 15 is an audit.** If they are not, it becomes a rewrite.
That is the whole reason this guide exists.

### What would make this go wrong

- **Skipping Step 1** and styling screens ad hoc — guarantees a Phase 15 rewrite
- **Building screens before shells** — every screen gets rebuilt when the shell arrives
- **Treating states as polish** — they are the bulk of the work and they surface last if deferred
- **Copying the prototypes' inline styles** — produces markup nobody can restyle
- **Designing around a logo bigger than ~100 px** — the raster assets cannot support it
