# UI Guide — Maieutic LMS

**Every screen in this product is built against this document.** If a view contradicts it, the view
is wrong. If this document contradicts the brand system in `sample ui/_ds/`, that design system
wins and this file gets fixed.

Read this before writing a single line of Blade. It is written to be followed by Claude Code as
much as by a person — when you ask Claude for a screen, tell it to read this file first.

---

## 0. How to use this with Claude

Start any UI task with:

> Read `docs/UI-GUIDE.md` in full before writing anything. Build [screen] following it exactly.
> Reuse the existing components in `resources/views/components/`. Do not invent new tokens,
> new colours, or a second version of an existing component.

Then check the output against §12 before you open a PR.

---

## 1. What we are building, and what we are not

Maieutic is a **premium learning-experience company**, not a coaching institute and not a generic
EdTech product. The reference points are Apple, Linear, Notion, Stripe. The anti-references are
Canva templates, gradient-heavy SaaS dashboards, and anything that looks like a course marketplace.

The house style is **editorial**: big type, big air, tight alignment, one or two colours per screen.
A page should read like a well-set magazine spread, not a control panel.

**Restraint is the signal of quality.** When in doubt, remove something.

### The sample is the floor, not the ceiling

`sample ui/Student.dc.html` shows the intended standard. Match its discipline — the type contrast,
the whitespace, the hairline borders — and then do better. What it does *not* show, and what we are
expected to add: real loading states, real empty states, real error states, and keyboard operability.

---

## 2. Ownership and sequencing

UI is no longer owned by one person. It is split into a **shared layer** and **feature screens**.

| Layer | Who owns it | Files |
|---|---|---|
| **Design foundation** — tokens, fonts, base layer | **Govind** (one-time), then frozen | `resources/css/app.css` |
| **Shared component library** | **Srivathsa** after the foundation lands | `resources/views/components/**` |
| **Layouts / shells** | **Srivathsa** | `resources/views/layouts/**` |
| **Feature screens** | **The person who owns that phase** | own subdirectory only |

### Feature screen subdirectories

Ownership is by **subdirectory**, which is what keeps merges conflict-free — the same trick that
let two 46-file and 73-file branches merge with zero overlap.

| Area | Owner |
|---|---|
| `Admin/Courses/**` — course builder | Govind |
| `Student/**` — dashboard, my courses, player | Govind |
| public catalogue + course detail | Govind |
| `Admin/Users/**`, `Admin/Settings/**` — admin shell | Srivathsa |
| `Assessment/**`, `Instructor/**` | Srivathsa |
| `Reporting/**`, mail templates | Shashank |

### The order this has to happen in

This sequencing is not a preference. Getting it wrong means rework.

1. **Govind lands the design foundation first** — §4's tokens replacing the current placeholder
   blue, plus the fonts. Small, fast, one PR.
2. **Everyone rebases immediately.** Srivathsa is mid-Phase-4; he rebases before going further,
   because every component he builds inherits these tokens.
3. **Srivathsa restyles the 12 shared components** against the new tokens, and finishes Phase 4's
   admin shell.
4. **Then** feature screens, in parallel, by owner.

> **Why this order.** Building an admin shell against placeholder-blue components and restyling
> afterwards means touching every file twice, and the second pass lands in someone else's
> directory. Foundation first is the only order that doesn't create rework.

### What has no UI

**Phase 3 has no UI at all.** It is schema, models and policies. There is nothing to style.

**Phase 5's course builder lives inside Phase 4's admin shell**, so it cannot be finished until
Srivathsa merges. The **public catalogue and course detail pages have no shell dependency** and can
be built at any time.

---

## 3. The rules nobody may break

1. **Never invent a colour.** Every colour comes from §4. No hex in a Blade file, ever.
2. **Never fork a component.** A second button component is a defect. Extend the existing one.
3. **No arbitrary Tailwind values** — `p-[13px]`, `text-[#00615c]`, `w-[347px]` are all rejected.
   If a value is needed twice, it is a token.
4. **No emoji. Anywhere.** Not in UI, not in copy, not in commit messages.
5. **Focus is always visible.** Never remove the focus ring. It is set globally in the base layer.
6. **Light mode only in V1.** Do not build a dark theme. Dark *sections* (deep teal panels) are a
   design device, not a theme.
7. **Backend policy is the only authority.** Hiding a button is never security — the policy check
   behind it is what matters. See the root `CLAUDE.md`.

---

## 4. Design tokens

These replace the placeholder palette currently in `resources/css/app.css`. Tailwind 4 is
configured CSS-first — there is no `tailwind.config.js`, everything lives in `@theme`.

```css
@theme {
    /* ---- Typography ------------------------------------------------ */
    /* Serif display is the hero. Sans for UI/body. Mono for eyebrows.  */
    --font-serif: 'Newsreader', Georgia, 'Times New Roman', serif;
    --font-sans:  'Hanken Grotesk', system-ui, -apple-system, 'Segoe UI', sans-serif;
    --font-mono:  'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;

    /* ---- Teal — the primary brand and the ONLY CTA colour ---------- */
    --color-teal-50:  #eaf2f1;
    --color-teal-100: #cde3e1;
    --color-teal-200: #a5ccc9;
    --color-teal-300: #6fada8;
    --color-teal-400: #3c8e88;
    --color-teal-500: #167871;
    --color-teal-600: #00615c;  /* Training Day Teal — base */
    --color-teal-700: #024e4a;
    --color-teal-800: #043b38;
    --color-teal-900: #052826;

    /* ---- Red Sea — a RARE accent. Not a second brand colour -------- */
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
    --color-neutral-50:  #faf9f6;  /* paper — the page background */
    --color-neutral-100: #f2f1ec;
    --color-neutral-200: #e5e3dc;  /* the hairline border workhorse */
    --color-neutral-300: #d2cfc6;
    --color-neutral-400: #b0aca1;
    --color-neutral-500: #8a867b;
    --color-neutral-600: #66625a;
    --color-neutral-700: #4a473f;
    --color-neutral-800: #2e2c27;
    --color-neutral-900: #1a1815;  /* ink — body text */

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
    --radius-card: 16px;   /* the default card */
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

### Semantic meaning — use the role, not the raw scale

| Role | Token | Notes |
|---|---|---|
| Page background | `neutral-50` | Warm paper. Never pure white. |
| Card surface | `neutral-0` | White on paper. |
| Heading text | `neutral-900` | Ink. |
| Body text | `neutral-800` | |
| Muted text | `neutral-500` | Metadata, captions. |
| Border | `neutral-200` | The hairline workhorse. |
| Brand / primary CTA | `teal-600` | The **only** CTA colour. |
| Brand hover | `teal-700` | |
| Brand pressed | `teal-800` | |
| Dark panels | `teal-900` | With white text. |
| Success | `honeydew` | |
| Warning | `melon` | |
| Danger / destructive | `red-600` | Also used for the rare accent. |

### Status colours are fixed

Green means success. Amber means warning. Red means danger or destructive. Never repurpose them
decoratively — a red badge must mean something is wrong.

### Fonts

Self-host via `@fontsource` packages, exactly as Instrument Sans is today. **No CDN links** — they
break the CSP and leak user IPs to a third party.

> **`package.json` is Track C's file (Shashank).** Adding the three font packages needs his sign-off
> and a recorded Rule 6 justification. Ask before adding. Do not edit `package.json` unilaterally.

---

## 5. Typography — the hero of the system

The signature of this brand is **the contrast between huge serif headlines and small tracked mono
labels**. If a screen has no type contrast, it is off-brand no matter how correct the colours are.

| Use | Family | Size | Weight | Tracking |
|---|---|---|---|---|
| Page title | serif | 30–38px | 600 | `-0.015em` |
| Section heading | serif | 20–24px | 600 | `-0.015em` |
| Card heading | serif | 18–19px | 600 | normal |
| Body | sans | 16px | 400 | normal |
| Small / secondary | sans | 14px | 400 | normal |
| Eyebrow / kicker | **mono, UPPERCASE** | 12px | 500 | `0.16em` |
| Metadata, counts | mono | 12–13px | 400 | `0.04em` |

**Line height:** 1.5–1.65 for body, 1.2 for headings, 1.05–1.15 for display.

**Measure:** cap body text at `68ch`. Long unbroken lines are the most common failure in a
first draft.

**Casing:** sentence case everywhere — "Course catalogue", never "Course Catalogue". UPPERCASE is
reserved exclusively for mono eyebrows, which are 1–4 words.

---

## 6. Layout and space

- Content max width **1240px**, gutters **24px**, page inset **32px** minimum.
- Vertical section rhythm on a **96px** cadence. Marketing/public pages should breathe more than
  admin tables.
- 4px base spacing scale: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80, 96, 128.
- **Left-align text by default.** Centred body copy is a brand violation. Centring is for short
  hero statements only.
- Generous, intentional negative space. If a screen feels crowded, remove an element rather than
  shrinking the gaps.

---

## 7. Components — extend, never fork

Twelve components already exist in `resources/views/components/`:

```
alert  badge  button  card  checkbox  empty-state
input  modal  pagination  select  table  textarea
```

**If you need a variant, add a prop to the existing component.** Do not create `button-2`,
`primary-button`, or a local `<div class="...">` that reimplements one. Rule 3 of the root
`CLAUDE.md` calls a second button component a defect, and it means it.

If a genuinely new primitive is needed, that is a conversation with Srivathsa (component library
owner), not a commit.

### Cards

White on paper, `1px` `neutral-200` hairline border, `16px` radius. **Hierarchy comes from borders
and spacing, not shadow.** A resting card has no shadow or `shadow-xs`; a soft shadow appears only
on hover or for overlays. No coloured left-border accent cards.

### Buttons

- **Primary** — solid `teal-600`, white text. **One per screen.** If you have two primary buttons,
  one of them is secondary.
- **Secondary** — `neutral-0` surface, `neutral-200` border, ink text.
- **Ghost** — text only, for tertiary actions.
- **Destructive** — `red-600`, and destructive actions always confirm first.

Radius 8–12px. Never pill-shaped buttons; pills are for badges and tags only.

### The "M" angle motif

The triangular geometry of the Maieutic "M" — a **single diagonal angle** at the edge or corner of
a card or callout, in two palette colours. Use it **sparingly**, only to add life to an otherwise
plain block. Never as a repeating pattern, never as a background texture.

---

## 8. States — all five, every time

A screen is not done when the happy path renders. Every data surface needs:

| State | Requirement |
|---|---|
| **Loading** | Skeletons matching final layout. No spinners as the primary device, no layout shift. |
| **Empty** | Use `<x-empty-state>`. Explain what would be here and give the action that creates it. Never a bare "No results". |
| **Error** | Say what failed and what to do next. Never expose a stack trace or a raw exception. |
| **Partial** | Long lists paginate. Never render 500 rows. |
| **Success** | Confirm destructive and creative actions with a consistent flash/toast. |

Empty states are the most commonly skipped and the most visible to a first-time user — the whole
product is empty on day one.

---

## 9. Interaction, motion, accessibility

**Hover** — buttons darken one step (`teal-600` → `teal-700`). Cards raise slightly: border darkens,
subtle shadow, ~1px lift. Links go to `teal-700` with a `0.18em` underline offset. **No colour-flip
surprises.**

**Press** — one step darker again (`teal-800`), optionally `scale(0.98)`. Subtle and tactile.

**Focus** — a visible `3px` teal ring on `:focus-visible`. Already set globally in the base layer.
**Never remove it.**

**Motion** — 120–320ms. `--ease-standard` for most, `--ease-out` for entrances. Fades and small
2–8px translations only. No bounces, no springs, no parallax. `prefers-reduced-motion` is already
respected in the base layer; do not bypass it with inline animation.

**Accessibility is WCAG 2.1 AA and it is not negotiable** (NFR-UX-03). Build it in — retrofitting it
in Phase 15 is far more expensive than getting it right now:

- Semantic HTML. A clickable `div` is a bug. Buttons are `<button>`, links are `<a>`.
- Every input has a real `<label>`. Placeholders are not labels.
- Body text contrast ≥ 4.5:1, large text ≥ 3:1. Check `neutral-500` on `neutral-50` before using it
  for anything that must be read.
- Full keyboard operability. Logical tab order. Modals trap focus and close on `Escape`.
- Icons are decorative — `aria-hidden` — unless they are the only content, in which case they need
  an accessible name.
- Never convey meaning by colour alone. A red border needs text next to it.

---

## 10. Voice and copy

Copy is part of the design, and bad copy makes good layout look cheap.

**Intelligent, calm, precise — a thoughtful mentor, never a hype marketer.** Speak to the learner as
"you"; "we" is used sparingly. Active voice, concrete verbs — *understand, question, build, master* —
never *empower, unlock, revolutionise*.

One clear message per screen. One primary action. If a word can be cut and the point survives, cut
it.

| Good | Bad |
|---|---|
| "Begin a session" | "Start Your Journey Today! 🚀" |
| "Answers fade. The questions stay." | "World's #1 Learning Platform!!!" |
| "3 questions · 92% complete" | "You're doing amazing!!" |
| "This course has no lessons yet." | "Oops! Nothing here 😢" |

Numerals for data. Em dashes for asides. Minimal exclamation marks. **No emoji, ever.**

---

## 11. Laravel specifics

- **Blade + Livewire 4 + Tailwind 4.** The `sample ui/` folder ships React components and a JS
  bundle — we take the **tokens and the rules** from it, not the code. Do not try to import
  `_ds_bundle.js`.
- The sample HTML uses **inline styles**. That is fine for a mockup and wrong for us. Use Tailwind
  utility classes bound to the tokens above.
- **No business logic in Blade or Livewire components** (Rule 16). Views orchestrate; Actions and
  Services decide.
- Livewire components live in `app/Livewire/{Area}/`, their views in
  `resources/views/livewire/{area}/`. Stay in your own subdirectory.
- Prefer server-rendered Blade. Reach for Livewire when a screen genuinely needs interactivity —
  the course builder does; a course detail page does not.
- Every screen renders behind a policy check. Never rely on a hidden button.

---

## 12. Before you open a UI PR

- [ ] No hex codes, no arbitrary Tailwind values, no invented tokens
- [ ] No new component that duplicates an existing one
- [ ] Loading, empty and error states all exist
- [ ] Keyboard: full tab order, visible focus, `Escape` closes modals
- [ ] Contrast checked on real token pairs
- [ ] Responsive at 360 / 768 / 1024 / 1440 / 1920
- [ ] Sentence case; no emoji; copy passes §10
- [ ] Type contrast present — serif headings against mono eyebrows
- [ ] `composer check` green — Pint, Larastan level 8, Pest
- [ ] Screenshot in the PR description

---

## 13. Open questions

These are not yet decided. Do not guess — ask.

1. **Font licensing.** Newsreader / Hanken Grotesk / JetBrains Mono are Google Fonts substitutes for
   the brand guideline's Lucida Sans and Posterama, which are not web-licensed. If Maieutic owns web
   licences for the originals, they replace these.
2. **Logo.** Only a white-background PNG exists. A transparent SVG and a reversed light-on-dark
   lockup are needed before any dark teal panel can carry the logo.
3. **Icon set.** Lucide is the assumed default. It needs self-hosting rather than a CDN, and
   `package.json` is Track C's file.
4. **Whether `sample ui/` belongs in the repository.** It is ~100KB of reference HTML plus a React
   bundle we do not consume. It probably belongs in `docs/design/` or in shared storage, not in the
   application root.
