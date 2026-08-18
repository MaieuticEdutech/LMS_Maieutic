@extends('layouts.landing')

@section('title', $organisation.' — learn skills that stay')

@section('content')
{{--
    Public landing page — reproduction of the Design-Compiler export in
    `sample landing ui/chnages/LMS Landing.dc.html`.

    ═════════════════════════════════════════════════════════════════════════
    THIS PAGE ADDRESSES THE LEARNER, NOT AN INSTITUTION.

    The brief in that folder's CLAUDE.md is explicit: Maieutic sells courses
    directly to students, the way Coursera or Springboard does — not to
    universities. So every line is written to "you", the person taking the
    course. There is no copy here about managing departments, assigning
    instructors, or running a campus, and none should be added.

    That is a positioning decision, not a wording preference. Copy aimed at an
    administrator on a page a student reads is the fastest way to make a
    product feel like it was not built for them.
    ═════════════════════════════════════════════════════════════════════════

    THE MARKUP AND INLINE STYLES ARE THE EXPORT'S. Only what cannot run in
    Blade is translated: <x-import … Button> to the app's <x-button>,
    <sc-for> to @foreach, <sc-if> to a plain render, <image-slot> to the
    placeholder partial.

    Variables (--fs-5xl, --teal-200 …) are declared in layouts.landing and
    point at the app's own theme tokens, so a brand change still reaches here.

    Class hooks (l-split, l-media …) exist only for the layout's small-screen
    media queries and carry no styling of their own.
--}}

<header style="position:sticky;top:0;z-index:50;background:rgba(250,249,246,0.86);backdrop-filter:blur(12px);border-bottom:1px solid var(--border)">
  <div style="max-width:1240px;margin:0 auto;padding:0 24px;height:68px;display:flex;align-items:center;justify-content:space-between;gap:24px">
    <a href="{{ route('home') }}" style="display:flex;align-items:center">
      <img src="{{ asset('images/logo-maieutic.png') }}" alt="{{ $organisation }}" style="height:34px;mix-blend-mode:multiply">
    </a>
    <nav class="l-nav" style="display:flex;align-items:center;gap:32px;font-size:15px;font-weight:var(--fw-medium)">
      <a href="#platform" style="color:var(--text-body)">How it works</a>
      <a href="#roles" style="color:var(--text-body)">For students</a>
      <a href="#trust" style="color:var(--text-body)">Security</a>
    </nav>
    <div style="display:flex;align-items:center;gap:12px">
      {{-- Filled teal rather than the export's ghost: asked for directly, and
           against a translucent header a ghost button is easy to miss. --}}
      @auth
        <x-button :href="auth()->user()->role->homePath()" size="sm" style="height:36px;padding-left:16px;padding-right:16px">Dashboard</x-button>
      @else
        <x-button :href="route('login')" size="sm" style="height:36px;padding-left:16px;padding-right:16px">Sign in</x-button>
      @endauth
    </div>
  </div>
</header>

<main id="main">

{{-- ══ HERO — full-bleed dark teal ══ --}}
<div style="background:var(--teal-900)">
<section class="l-section" style="max-width:1240px;margin:0 auto;padding:96px 24px 96px">
  <div class="l-split" style="display:grid;grid-template-columns:6fr 5fr;gap:56px;align-items:center">
    <div style="display:flex;flex-direction:column;align-items:flex-start;gap:28px">
      <div style="font-family:var(--font-mono);font-size:var(--fs-eyebrow);letter-spacing:var(--ls-eyebrow);text-transform:uppercase;color:var(--teal-200)">{{ $organisation }}</div>
      <h1 class="l-hero-h1" style="margin:0;font-family:var(--font-serif);font-weight:var(--fw-medium);font-size:74px;line-height:var(--lh-tight);letter-spacing:var(--ls-tight);color:var(--text-inverse);text-wrap:balance">Learn skills that stay — by <em style="color:var(--teal-200);font-style:normal">questioning, not cramming</em>.</h1>
      <p style="margin:0;max-width:52ch;font-size:var(--fs-xl);line-height:var(--lh-relaxed);color:var(--teal-100);text-wrap:pretty">Courses in the subjects that matter to you, taught the Socratic way. Enrol online, learn at your pace, test yourself, and watch real progress build.</p>
      <div style="display:flex;align-items:center;gap:16px;margin-top:8px;flex-wrap:wrap">
        <x-button :href="Route::has('catalogue.index') ? route('catalogue.index') : '#platform'" variant="secondary" size="lg" style="height:54px;padding-left:24px;padding-right:24px;font-size:16px">Explore the courses</x-button>

        {{-- A plain link rather than <x-button variant="ghost">: that variant
             is built for light surfaces (dark text, grey hover) and would be
             nearly invisible here. Styled inline to the export's own values. --}}
        <a href="#platform" class="l-ghost-dark" style="display:inline-flex;align-items:center;justify-content:center;height:54px;padding:0 20px;border-radius:8px;font-size:16px;font-weight:var(--fw-medium);color:var(--teal-100);text-decoration:none">How it works</a>
      </div>
      <div style="font-size:var(--fs-sm);color:var(--teal-300)">Browse every course free — enrol when you're ready.</div>
    </div>
    <div style="position:relative">
      {{-- 4/3 is the photograph's own ratio, not a guess. The slot was
                 built for a tall crop (a fixed 520px), but this image is
                 landscape and its subject runs the full width — the student on
                 the left, the product on the laptop centre-right. Forcing it
                 into a portrait box with object-fit:cover cut off one or the
                 other. Matching the ratio shows all of it and never distorts. --}}
            <div class="l-media l-media-hero" style="position:relative;aspect-ratio:4/3;border-radius:var(--radius-lg);overflow:hidden">
        @include('partials.landing-image-slot', [
          'src' => 'images/landing/hero.jpg',
          'alt' => 'A student in headphones taking handwritten notes on a tablet while a laptop beside her shows her Maieutic dashboard: course progress, the next lesson, and her enrolled courses.',
          'caption' => 'A student learning with the Maieutic dashboard open',
          'width' => 1448,
          'height' => 1086,
        ])
      </div>
      <div style="position:absolute;left:-14px;bottom:26px;width:120px;height:14px;background:var(--teal-600);transform:skewX(-32deg)"></div>
      <div style="position:absolute;left:-14px;bottom:8px;width:74px;height:14px;background:var(--red-600);transform:skewX(-32deg)"></div>
    </div>
  </div>
</section>
</div>

{{-- ══ HOW IT WORKS ══ --}}
<section id="platform" style="border-top:1px solid var(--border);scroll-margin-top:68px">
  <div style="max-width:1240px;margin:0 auto;padding:96px 24px 0;display:flex;flex-direction:column;gap:24px">
    <div style="font-family:var(--font-mono);font-size:var(--fs-eyebrow);letter-spacing:var(--ls-eyebrow);text-transform:uppercase;color:var(--text-brand)">How it works</div>
    <h2 class="l-display" style="margin:0;font-family:var(--font-serif);font-weight:var(--fw-medium);font-size:var(--fs-5xl);line-height:var(--lh-snug);letter-spacing:var(--ls-tight);color:var(--text-heading);max-width:24ch">Everything you need to learn, nothing in the way.</h2>
  </div>

  @php
      $features = [
          [
              'number' => '01', 'kicker' => 'Courses',
              'title' => 'Courses built as clear paths',
              'body' => 'Every course is organised into modules and lessons — video, reading, and practice — so you always know where you are and what comes next.',
              'points' => [
                  'Step-by-step modules: watch, read, practise',
                  'Browse any course’s full outline before you enrol',
                  'Find courses by subject and level',
              ],
              'image' => 'Course builder UI — screenshot',
              'src' => 'images/landing/feature-courses.jpg',
              'alt' => 'A course dashboard: the lesson playing at the top, bookmarked moments beneath it, course categories, and a right-hand rail showing path progress at 65% with each module ticked off.',
              // A SCREENSHOT, NOT A PHOTOGRAPH, and square. Cropping a photo
              // loses scenery; cropping a screenshot loses the interface being
              // shown — here the header and the whole recent-videos list. The
              // row takes the image's own ratio so all of it is legible.
              'ratio' => '1 / 1',
              'padding' => '72px 24px',
              'flip' => false,
          ],
          [
              'number' => '02', 'kicker' => 'Assessment',
              'title' => 'Test yourself, know for sure',
              'body' => 'Quizzes and exams with instant, automatic grading. Your results arrive the moment they’re ready — no waiting, no guessing.',
              'points' => [
                  'Multiple question types, fair attempt limits',
                  'Pass an assessment and the lesson completes itself',
                  'Results delivered to you instantly, every time',
              ],
              'image' => 'Assessment screen — screenshot',
              'src' => 'images/landing/feature-assessment.jpg',
              'alt' => 'An assessments screen: counts of total, completed, in-progress and upcoming assessments, a list of quizzes and assignments each with a progress bar and a Continue button, and a weekly progress ring at 70% beside recent results.',
              'ratio' => '4 / 3',
              'padding' => '0 24px 72px',
              'flip' => true,
          ],
          [
              'number' => '03', 'kicker' => 'Progress',
              'title' => 'See your progress build',
              'body' => 'Every lesson you finish rolls up to course completion. Pick up exactly where you left off, and see how far you’ve come at a glance.',
              'points' => [
                  'Lesson-by-lesson progress, tracked automatically',
                  'Completion notifications the moment you finish',
                  'Your learning history, always in one place',
              ],
              'image' => 'Progress dashboard — screenshot',
              'src' => 'images/landing/feature-progress.jpg',
              'alt' => 'A progress dashboard: lessons completed, course progress, time spent and current streak across the top, a course-completion chart climbing week by week, and a recent-activity list of finished lessons and passed quizzes.',
              'ratio' => '3 / 2',
              'padding' => '0 24px 96px',
              'flip' => false,
          ],
      ];
  @endphp

  @foreach ($features as $feature)
    <div class="l-split" style="max-width:1240px;margin:0 auto;padding:{{ $feature['padding'] }};display:grid;grid-template-columns:1fr 1fr;gap:80px;align-items:center">
      @if ($feature['flip'])
        <div class="l-media l-flip" @style(['position:relative;border-radius:var(--radius-lg);overflow:hidden;order:0', 'aspect-ratio:'.($feature['ratio'] ?? '') => isset($feature['ratio']), 'height:400px' => ! isset($feature['ratio'])])>
          @include('partials.landing-image-slot', ['caption' => $feature['image'], 'src' => $feature['src'], 'alt' => $feature['alt'] ?? null])
        </div>
      @endif

      <div style="display:flex;flex-direction:column;gap:20px">
        <div style="font-family:var(--font-mono);font-size:var(--fs-eyebrow);letter-spacing:var(--ls-eyebrow);text-transform:uppercase;color:var(--text-muted)">{{ $feature['number'] }} · {{ $feature['kicker'] }}</div>
        <h3 class="l-display" style="margin:0;font-family:var(--font-serif);font-weight:var(--fw-medium);font-size:var(--fs-4xl);line-height:var(--lh-heading);letter-spacing:var(--ls-tight);color:var(--text-heading)">{{ $feature['title'] }}</h3>
        <p style="margin:0;font-size:var(--fs-lg);line-height:var(--lh-relaxed);color:var(--text-muted);max-width:56ch">{{ $feature['body'] }}</p>
        <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:12px;font-size:var(--fs-base);color:var(--text-body)">
          @foreach ($feature['points'] as $point)
            <li style="display:flex;gap:12px;align-items:baseline"><span style="width:6px;height:6px;background:var(--teal-600);flex:0 0 auto;transform:translateY(-2px)"></span>{{ $point }}</li>
          @endforeach
        </ul>
      </div>

      @unless ($feature['flip'])
        <div class="l-media" @style(['position:relative;border-radius:var(--radius-lg);overflow:hidden', 'aspect-ratio:'.($feature['ratio'] ?? '') => isset($feature['ratio']), 'height:400px' => ! isset($feature['ratio'])])>
          @include('partials.landing-image-slot', ['caption' => $feature['image'], 'src' => $feature['src'], 'alt' => $feature['alt'] ?? null])
        </div>
      @endunless
    </div>
  @endforeach
</section>

{{-- ══ THE PARTS THAT JUST WORK ══ --}}
@php
    $gridFeatures = [
        ['title' => 'Easy enrollment & payments', 'body' => 'Enrol and pay online in minutes — secure checkout, instant access.', 'icon' => '<rect width="20" height="14" x="2" y="5" rx="2"></rect><path d="M2 10h20"></path>'],
        ['title' => 'Protected media delivery', 'body' => 'Your course videos and materials stream securely, on any device.', 'icon' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="m9 12 2 2 4-4"></path>'],
        ['title' => 'Your data stays yours', 'body' => 'Your account, your courses, your results — private to you.', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'],
        ['title' => 'Browse before you buy', 'body' => 'Browse every course and its outline before spending a rupee.', 'icon' => '<path d="m16 6 4 14"></path><path d="M12 6v14"></path><path d="M8 8v12"></path><path d="M4 4v16"></path>'],
        ['title' => 'Notifications that land', 'body' => 'Enrollment confirmations, results, and completion emails — right on time.', 'icon' => '<rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>'],
        ['title' => 'Nothing gets lost', 'body' => 'A platform that keeps a careful record, so nothing is ever lost.', 'icon' => '<path d="M15 12h-5"></path><path d="M15 8h-5"></path><path d="M19 17V5a2 2 0 0 0-2-2H4"></path><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3"></path>'],
    ];
@endphp

<section style="background:var(--surface-card);border-top:1px solid var(--border);border-bottom:1px solid var(--border)">
  <div class="l-section" style="max-width:1240px;margin:0 auto;padding:88px 24px;display:flex;flex-direction:column;gap:48px">
    <h2 class="l-display" style="margin:0;font-family:var(--font-serif);font-weight:var(--fw-medium);font-size:var(--fs-4xl);line-height:var(--lh-snug);letter-spacing:var(--ls-tight);color:var(--text-heading)">And the parts that just work</h2>
    <div class="l-grid-3" style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px">
      @foreach ($gridFeatures as $f)
        <div class="grid-card" style="border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px;display:flex;flex-direction:column;gap:14px;background:var(--surface-card);transition:border-color 200ms,box-shadow 200ms">
          <span style="display:inline-flex;width:22px;height:22px;color:var(--teal-600)">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $f['icon'] !!}</svg>
          </span>
          <div style="font-weight:var(--fw-semibold);font-size:var(--fs-lg);color:var(--text-heading)">{{ $f['title'] }}</div>
          <div style="font-size:var(--fs-base);line-height:var(--lh-normal);color:var(--text-muted)">{{ $f['body'] }}</div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ══ FOR STUDENTS ══ --}}
@php
    $steps = [
        ['label' => 'Browse', 'accent' => 'var(--teal-600)', 'title' => 'A clean catalogue', 'body' => 'Find the right course by category and level, see exactly what it covers, and enrol in a few clicks.'],
        ['label' => 'Learn', 'accent' => 'var(--red-600)', 'title' => 'Lessons that track themselves', 'body' => 'Your enrolled courses in one place — pick up where you left off, with progress recorded as you go.'],
        ['label' => 'Prove it', 'accent' => 'var(--teal-600)', 'title' => 'Results that arrive promptly', 'body' => "Take assessments, get graded automatically, and see your results the moment they're ready."],
    ];
@endphp

<section id="roles" class="l-section" style="max-width:1240px;margin:0 auto;padding:96px 24px;scroll-margin-top:68px">
  <div style="display:flex;flex-direction:column;gap:24px;margin-bottom:56px">
    <div style="font-family:var(--font-mono);font-size:var(--fs-eyebrow);letter-spacing:var(--ls-eyebrow);text-transform:uppercase;color:var(--text-brand)">For students</div>
    <h2 class="l-display" style="margin:0;font-family:var(--font-serif);font-weight:var(--fw-medium);font-size:var(--fs-5xl);line-height:var(--lh-snug);letter-spacing:var(--ls-tight);color:var(--text-heading);max-width:22ch">Learn without friction.</h2>
  </div>
  <div class="l-grid-3" style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px">
    @foreach ($steps as $step)
      <div style="border:1px solid var(--border);border-radius:var(--radius-lg);padding:32px;display:flex;flex-direction:column;gap:16px;position:relative;overflow:hidden">
        <div style="position:absolute;top:0;right:0;width:44px;height:10px;background:{{ $step['accent'] }};transform:skewX(-32deg) translateX(10px)"></div>
        <div style="font-family:var(--font-mono);font-size:var(--fs-eyebrow);letter-spacing:var(--ls-eyebrow);text-transform:uppercase;color:var(--text-muted)">{{ $step['label'] }}</div>
        <div style="font-family:var(--font-serif);font-size:var(--fs-2xl);color:var(--text-heading)">{{ $step['title'] }}</div>
        <div style="font-size:var(--fs-base);line-height:var(--lh-relaxed);color:var(--text-muted)">{{ $step['body'] }}</div>
      </div>
    @endforeach
  </div>
</section>

{{-- ══ SECURITY ══ --}}
@php
    $guarantees = [
        ['title' => 'Payments you can trust', 'body' => 'Your enrollment activates only after your payment is securely verified — so access is guaranteed, every time.'],
        ['title' => 'Content that stays yours', 'body' => 'Course content is delivered only to enrolled learners, through a protected path — your paid content stays exclusive.'],
        ['title' => 'A record you can rely on', 'body' => 'Every enrollment, payment, and result is recorded — your history is always there when you need it.'],
    ];
@endphp

<section id="trust" style="background:var(--teal-900);scroll-margin-top:68px">
  <div class="l-split l-section" style="max-width:1240px;margin:0 auto;padding:96px 24px;display:grid;grid-template-columns:5fr 7fr;gap:80px;align-items:start">
    <div style="display:flex;flex-direction:column;gap:24px">
      <div style="font-family:var(--font-mono);font-size:var(--fs-eyebrow);letter-spacing:var(--ls-eyebrow);text-transform:uppercase;color:var(--teal-200)">Security</div>
      <h2 class="l-display" style="margin:0;font-family:var(--font-serif);font-weight:var(--fw-medium);font-size:var(--fs-5xl);line-height:var(--lh-snug);letter-spacing:var(--ls-tight);color:var(--text-inverse)">Your learning is protected.</h2>
      <p style="margin:0;font-size:var(--fs-lg);line-height:var(--lh-relaxed);color:var(--teal-100);max-width:44ch">Payments, course access, and your results are protected at every step — not just on the surface.</p>
    </div>
    <div style="display:flex;flex-direction:column;gap:0">
      @foreach ($guarantees as $g)
        <div style="display:flex;gap:24px;padding:28px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--border-inverse)' : '' }}">
          <div style="font-family:var(--font-mono);font-size:var(--fs-sm);color:var(--teal-300);flex:0 0 40px">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
          <div style="display:flex;flex-direction:column;gap:8px">
            <div style="font-weight:var(--fw-semibold);font-size:var(--fs-lg);color:var(--text-inverse)">{{ $g['title'] }}</div>
            <div style="font-size:var(--fs-base);line-height:var(--lh-relaxed);color:var(--teal-100)">{{ $g['body'] }}</div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ══ CLOSING ══ --}}
<section class="l-section" style="max-width:1240px;margin:0 auto;padding:120px 24px;display:flex;flex-direction:column;align-items:center;gap:32px;text-align:center">
  <h2 class="l-display" style="margin:0;font-family:var(--font-serif);font-weight:var(--fw-medium);font-size:var(--fs-6xl);line-height:var(--lh-tight);letter-spacing:var(--ls-tight);color:var(--text-heading);max-width:18ch;text-wrap:balance">Start learning by asking.</h2>
  <p style="margin:0;font-size:var(--fs-xl);line-height:var(--lh-relaxed);color:var(--text-muted);max-width:44ch">Real courses, a clear method, and progress you can see. Start with any subject — start with a question.</p>
</section>

</main>

<footer style="border-top:1px solid var(--border)">
  <div class="l-split" style="max-width:1240px;margin:0 auto;padding:40px 24px;display:flex;align-items:center;justify-content:space-between;gap:24px">
    <img src="{{ asset('images/logo-maieutic.png') }}" alt="{{ $organisation }}" style="height:26px;mix-blend-mode:multiply">
    <div style="display:flex;gap:28px;font-size:var(--fs-sm)">
      <a href="#platform" style="color:var(--text-muted)">Platform</a>
      <a href="#roles" style="color:var(--text-muted)">For students</a>
      <a href="#trust" style="color:var(--text-muted)">Security</a>
    </div>
    <div style="font-size:var(--fs-sm);color:var(--text-subtle)">© {{ now()->year }} {{ $organisation }}. All rights reserved.</div>
  </div>
</footer>
@endsection
