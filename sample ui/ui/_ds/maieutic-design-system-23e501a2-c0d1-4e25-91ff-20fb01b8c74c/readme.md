# Maieutic Design System

A premium, editorial design system for **Maieutic** — a premium learning-experience company (not a traditional EdTech brand). The system prioritises clarity over decoration: typography is the hero, supported by generous whitespace, a strong grid, and a restrained, sophisticated palette. Reference points are Apple, Linear, Notion, Stripe, Pentagram and Porto Rocha — never generic coaching-institute or Canva aesthetics.

> *maieutic* (adj.) — relating to the Socratic method of drawing out ideas latent in the mind. The brand is about helping learners **understand, trust, and engage**.

---

## Sources

- `uploads/Maieutic brand guideline - DRAFT July 2 2025.pdf` — "Maieutic Edutech Brand Guidelines, Updated July 2025" (8 pp, DRAFT). Contains: logo + usage rules, primary/secondary/tertiary color palettes with hex/RGB/CMYK, two font options, usage instructions, the triangular "M" graphic motif, and photo-style direction. The logo image was extracted to `assets/logo-maieutic.png`.

No codebase, Figma, or product screens were provided. This is a **brand-guidelines-only** build: the component inventory is a standard editorial set sized to the brand; UI kits and slides are original compositions that express the brand rather than recreations of an existing product.

---

## ⚠️ Substitutions & caveats (please confirm)

1. **Fonts substituted.** The guideline lists Lucida Sans / Mangal (Option 1) and Posterama / Franklin Gothic (Option 2). None are web-licensed or align with the premium-editorial direction. Replaced with Google Fonts:
   - **Newsreader** (serif) — editorial display, headlines, pull-quotes
   - **Hanken Grotesk** — UI + body
   - **JetBrains Mono** — eyebrows, labels, metadata
   If the brand owns web licenses for the original fonts, send the files and I'll swap them in `tokens/fonts.css`.
2. **Logo has a white background** (extracted from the PDF, no vector/transparent version supplied). Per guideline it must not be recolored or placed on busy/patterned backgrounds, so it currently sits on light surfaces only. A transparent SVG/PNG and a reversed (light-on-dark) lockup would help — please provide if available.
3. **Iconography substituted** with [Lucide](https://lucide.dev) (CDN) — no icon set existed in the source. See ICONOGRAPHY.
4. The guideline's "Font Colors: Training Day Teal #dbdcdc" appears to be a typo (that hex is a light grey, not the teal). Treated as a neutral light grey.

---

## Index / manifest

**Root**
- `styles.css` — global entry (imports only). Consumers link this.
- `readme.md` — this file.
- `SKILL.md` — Agent-Skills-compatible wrapper.
- `thumbnail.html` — homepage tile.

**`tokens/`** — CSS custom properties (all reachable from `styles.css`)
- `fonts.css` (@font-face via Google), `colors.css`, `typography.css`, `spacing.css`, `effects.css` (radius/shadow/motion), `semantic.css` (aliases), `base.css` (element defaults).

**`components/`** — reusable React primitives (see Components below).

**`ui_kits/web/`** — Maieutic marketing site recreation (home, program detail, journal article).

**`slides/`** — sample presentation slide types.

**`guidelines/`** & specimen cards — `@dsCard`-tagged HTML populating the Design System tab.

**`assets/`** — `logo-maieutic.png` and derived brand marks.

---

## CONTENT FUNDAMENTALS

**Voice.** Intelligent, calm, and precise — a thoughtful mentor, never a hype-driven marketer. Confident without shouting. Copy is spare: one clear message per design, one primary call to action. If a word can be cut and the point survives, cut it.

**Person.** Speak to the learner as **"you"**; the brand is **"we"** used sparingly. Favour the active voice and concrete verbs (*understand, question, build, master*) over abstract claims (*empower, unlock, revolutionise*).

**Casing.** Sentence case for headlines and UI ("Learn by questioning", not "Learn By Questioning"). Reserve UPPERCASE only for mono eyebrows/kickers, which are short (1–4 words) and tracked wide.

**Tone by content.** Match register to gravity — the more serious the material, the more reserved the language and colour. Add personality through restraint, not volume.

**Punctuation & numerals.** Minimal exclamation; em dashes for editorial asides. Numerals for data ("3 questions", "92% completion"). No trailing hype.

**Emoji.** Never. No emoji, no cartoon illustration, no meme cadence.

**Examples**
- Eyebrow: `THE SOCRATIC METHOD`
- Headline: *"Answers fade. The questions stay."*
- Sub: "A learning experience built on inquiry, not information dumps."
- CTA: "Begin a session" / "Explore programs" (verb-first, singular intent)
- Avoid: "Unlock your potential today! 🚀", "World's #1 EdTech platform!!!"

---

## VISUAL FOUNDATIONS

**Overall.** Editorial and architectural: big type, big air, tight alignment. The page reads like a well-set magazine spread, not a dashboard. One or two colours per composition — restraint is the signal of quality.

**Colour.** Primary is **Training Day Teal `#00615c`** — the hero and the single CTA colour. **Red Sea `#800d07`** is a rare accent (it also lives in the logo). Neutrals are **warm**: paper `#faf9f6` → ink `#1a1815`, giving an off-white, printed-page feel rather than clinical white/grey. Secondary (melon, lemon, honeydew, blueberry, grape) and tertiary tints (marigold, dandelion, rose, hosta, bluestar, lavender) appear **sparingly** as accents, soft section backgrounds, or overlays — generally two–three colours max per application. Headlines may lean teal (a brand rule); body is ink on paper, white on dark.

**Typography.** The hero. Newsreader serif carries oversized display headlines and pull-quotes with tight tracking (`-0.015em`) and balanced wrapping. Hanken Grotesk handles UI and body at generous line-height (1.5–1.65) and a ~68ch measure. JetBrains Mono sets small uppercase eyebrows with wide tracking (`0.16em`). Strong contrast between huge serif headlines and small tracked mono labels is a signature.

**Layout & grid.** Editorial 12-column grid, `--content-max: 1240px`, consistent gutters (`24px`), left-aligned text by default (per guideline). Vertical rhythm on an ~96px section cadence. Generous, intentional negative space; nothing is crowded.

**The "M" angle motif.** The defining decorative element: the triangular geometry of the Maieutic "M" (teal + red angles). Reproduced as a **single diagonal angle** at the edge/corner of a card, callout, or media frame — using two palette colours — used *sparingly*, only to draw attention or add life to plain text. Never busy, never a full pattern.

**Backgrounds.** Predominantly flat warm paper or white. No aggressive gradients. Occasional soft tint blocks (marigold, teal-50) for section separation. Dark sections use deep teal (`--teal-900`) with white text. No textures, no noise, no photographic hero wallpaper by default.

**Imagery.** Authentic, diverse, natural — real people learning, collaborating, problem-solving, interacting with technology. Lighter-toned photography to balance the saturated palette; approachable, clean, story-giving composition with interesting angles. Often faces are *not* required (hands, devices, lines of connection). Avoid: fake corporate smiles, graduation caps, staged classrooms, stock clichés. In this system, imagery is shown via labelled placeholders for the user to fill.

**Corner radii.** Moderate and consistent — cards `16px` (`--radius-lg`), inputs/buttons `8–12px`, pills `full`. Never pill-everything; never sharp-everything.

**Cards.** White on paper, `1px` hairline border (`--neutral-200`), `16px` radius, and a *soft* shadow only on hover/elevation (`--shadow-sm`/`md`). Hierarchy comes from borders and spacing more than shadow. No coloured left-border-accent cards.

**Borders.** Hairline `1px` `--neutral-200` is the workhorse; `--neutral-300` for stronger separation; teal border for brand emphasis. Dividers are thin and quiet.

**Shadows.** Minimal and warm-tinted (rgba of ink). `xs`/`sm` for resting cards, `md`/`lg` for overlays (dialogs, menus). Never heavy drop shadows or glows.

**Motion.** Quiet and precise. Short durations (120–320ms), `cubic-bezier(0.22,0.61,0.36,1)` standard easing / `(0.16,1,0.3,1)` for entrances. Fades and small transl(2–8px) — no bounces, no springs, no parallax gimmicks. Respect reduced-motion.

**Hover states.** Buttons darken (teal-600→700). Cards raise slightly (border darkens, subtle shadow, ~1px lift). Links change to `--brand-hover` with a 0.18em underline offset. No colour-flip surprises.

**Press states.** Darken one further step (teal-800) and/or a `0.98` scale — subtle, tactile, never a big squish.

**Focus.** Visible `3px` teal ring (`--shadow-focus`, `rgba(0,97,92,.22)`) on `:focus-visible`. Accessibility is non-negotiable (light-on-dark / dark-on-light contrast per guideline).

**Transparency & blur.** Used sparingly — sticky headers get a translucent paper background with `backdrop-filter: blur` and a hairline underline. Overlays use a low-opacity ink scrim. No frosted-glass everywhere.

---

## ICONOGRAPHY

No icon set existed in the source. The system uses **[Lucide](https://lucide.dev)** (via CDN) — a clean, geometric, `1.75px`-stroke open-source set that matches the minimal editorial tone. Guidelines:
- Stroke icons only, `1.5–2px`, matched to text size (typically 18–20px inline, 16px in dense UI).
- `currentColor` so icons inherit text colour; teal or ink, never multicolour.
- Icons *support* text, they don't replace it. Use sparingly — the brand is type-led, not icon-led.
- **No emoji. No unicode glyph icons. No hand-drawn SVG.**
- If the brand adopts a proprietary icon set later, swap the CDN link and document here.

**Intentional additions** (beyond a from-scratch standard set): none structural — the component list is the standard editorial primitive set. An `Icon` wrapper around Lucide is provided so consumers reference glyphs consistently.

---

## Components

Grouped under `components/`. Public API is the PascalCase export; consume via `window.Maieutic.<Name>` after loading `_ds_bundle.js`.

- **forms/** — Button, IconButton, Input, Textarea, Select, Checkbox, Radio, Switch
- **data-display/** — Card, Badge, Tag, Avatar, Icon
- **feedback/** — Dialog, Tooltip, Toast
- **navigation/** — Tabs

## UI kits

- **web/** — Maieutic marketing site: Home (editorial hero), Program detail, Journal article. Interactive click-through composed from the primitives.

## Slides

Sample presentation slide types (16:9): Title, Section, Statement/Quote, Content, Stat, Closing.
