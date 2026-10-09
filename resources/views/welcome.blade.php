@extends('layouts.landing')

@section('title', $organisation.' — learn skills that stay')

@section('content')
{{--
    Public landing page.

    THIS PAGE ADDRESSES THE LEARNER, NOT AN INSTITUTION. Every line is written
    to "you", the person taking the course. No copy about managing departments
    or running a campus belongs here.

    NOTHING HERE IS INVENTED. No testimonials, no learner counts, no course
    names: the catalogue is the source of truth for courses and this page
    links to it instead of pretending. Every claim below is a shipped feature.
    The only figures on the page are inside clearly decorative UI samples.

    Styling for every l-* class lives in layouts.landing. Section order:

      hero        gradient panel · floating glass cards · stats bar overlap
      steps       three colourful step cards joined by a dashed path
      bento       feature grid with device-framed screenshots
      quote       the Socratic idea, in one line
      certificate dark band · CSS-drawn certificate
      faq         accordions, two columns
      closing     gradient band with the clear next step
      footer
--}}

@php
    $catalogueUrl = Route::has('catalogue.index') ? route('catalogue.index') : '#how';
    $registerUrl  = Route::has('register') ? route('register') : route('login');

    $check = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
@endphp

{{-- ══ HEADER ══ --}}
<header class="l-header">
  <div class="l-wrap l-header-inner">
    <a href="{{ route('home') }}" class="l-logo"><img src="{{ asset('images/logo-maieutic-edutech.png') }}" alt="{{ $organisation }}"></a>
    <nav class="l-nav" aria-label="Primary">
      <a href="{{ $catalogueUrl }}">Courses</a>
      <a href="#how">How it works</a>
      <a href="#inside">What's inside</a>
      <a href="#faq">FAQ</a>
    </nav>
    <div class="l-header-actions">
      @auth
        <a href="{{ auth()->user()->role->homePath() }}" class="l-btn l-btn-amber l-btn-sm">Dashboard</a>
      @else
        <a href="{{ $registerUrl }}" class="l-nav-link">Create account</a>
        <a href="{{ route('login') }}" class="l-btn l-btn-amber l-btn-sm">Sign in</a>
      @endauth
    </div>
  </div>
</header>

<main id="main">

{{-- ══ HERO ══ --}}
<section class="l-hero">
  <div class="l-blob l-blob-a" aria-hidden="true"></div>
  <div class="l-blob l-blob-b" aria-hidden="true"></div>
  <div class="l-blob l-blob-c" aria-hidden="true"></div>
  <div class="l-grid-lines" aria-hidden="true"></div>

  <div class="l-wrap l-hero-grid">
    <div class="l-reveal l-stagger l-hero-copy">
      <span class="l-pill"><span class="l-pill-dot"></span>Admissions open · Learn at your own pace</span>
      <h1 class="l-hero-h1">Learn skills that <em>stay</em> — by questioning, not cramming.</h1>
      <p class="l-hero-lead">Courses in the subjects that matter to you, taught the Socratic way. Enrol online, learn at your pace, test yourself, and watch real progress build.</p>
      <div class="l-hero-actions">
        <a href="{{ $catalogueUrl }}" class="l-btn l-btn-amber l-btn-lg">Explore the courses <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        <a href="#how" class="l-btn l-btn-ghost-light l-btn-lg">See how it works</a>
      </div>
      <ul class="l-proof">
        <li><span class="l-proof-check">{!! $check !!}</span>Browse every course free</li>
        <li><span class="l-proof-check">{!! $check !!}</span>Instant, automatic grading</li>
        <li><span class="l-proof-check">{!! $check !!}</span>Certificates anyone can verify</li>
      </ul>
    </div>

    <div class="l-reveal l-reveal-delay l-hero-visual">
      <div class="l-photo">
        @include('partials.landing-image-slot', [
          'src' => 'images/landing/hero.jpg',
          'alt' => 'A student in headphones taking handwritten notes on a tablet while a laptop beside her shows her Maieutic dashboard.',
          'caption' => 'A student learning with the Maieutic dashboard open',
          'width' => 1448,
          'height' => 1086,
        ])
      </div>

      {{-- Illustrative UI samples. Decorative only. --}}
      <div class="l-glass l-glass-progress l-float" aria-hidden="true">
        <svg class="l-ring" width="56" height="56" viewBox="0 0 52 52">
          <circle cx="26" cy="26" r="22" fill="none" stroke="rgba(0,97,92,0.12)" stroke-width="5"/>
          <circle class="l-ring-fill" cx="26" cy="26" r="22" fill="none" stroke="var(--teal-600)" stroke-width="5" stroke-linecap="round" transform="rotate(-90 26 26)"/>
        </svg>
        <div>
          <div class="l-glass-label">Course progress</div>
          <div class="l-glass-value">65% <span>Module 4 of 6</span></div>
          <div class="l-bar"><div class="l-bar-fill"></div></div>
        </div>
      </div>

      <div class="l-glass l-glass-quiz l-float l-float-slow" aria-hidden="true">
        <span class="l-glass-icon l-glass-icon-teal"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
        <div>
          <div class="l-glass-label">Quiz graded</div>
          <div class="l-glass-strong">9 / 10 · Passed</div>
        </div>
      </div>

      <div class="l-glass l-glass-cert l-float l-float-slower" aria-hidden="true">
        <span class="l-glass-icon l-glass-icon-amber"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/></svg></span>
        <div>
          <div class="l-glass-label">Certificate issued</div>
          <div class="l-glass-strong">Verified online</div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ══ HOW IT WORKS — three steps ══ --}}
@php
    $steps = [
        ['tone' => 'teal',  'label' => 'Browse',   'title' => 'Pick a course you can see inside', 'body' => 'Find the right course by subject and level, read its full outline, and enrol in a few clicks — no entrance test, no paperwork.', 'icon' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>'],
        ['tone' => 'amber', 'label' => 'Learn',    'title' => 'Lessons that track themselves',     'body' => 'Watch, read and practise at your own pace. Progress is saved as you go, so you always pick up exactly where you left off.', 'icon' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>'],
        ['tone' => 'red',   'label' => 'Prove it', 'title' => 'Pass, and earn the certificate',    'body' => 'Take the assessments, get graded automatically, and finish with a certificate that anyone can verify online.', 'icon' => '<circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/>'],
    ];
@endphp
<section id="how" class="l-section-pad">
  <div class="l-wrap">
    <div class="l-head l-reveal l-stagger">
      <span class="l-eyebrow">How it works</span>
      <h2 class="l-h2">Three steps from curious to certified.</h2>
      <p class="l-sub">Everything you need to learn, and nothing in the way.</p>
    </div>
    {{-- An open journey line: three nodes on one track, no boxes. --}}
    <ol class="l-journey l-reveal">
      @foreach ($steps as $step)
        <li class="l-jstep l-jstep-{{ $step['tone'] }} l-stagger-item" style="--i:{{ $loop->index }}">
          <div class="l-jnode">
            <span class="l-jnum">{{ $loop->iteration }}</span>
            <span class="l-jicon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $step['icon'] !!}</svg></span>
          </div>
          <span class="l-jlabel">{{ $step['label'] }}</span>
          <h3 class="l-h3">{{ $step['title'] }}</h3>
          <p class="l-body">{{ $step['body'] }}</p>
        </li>
      @endforeach
    </ol>
  </div>
</section>

{{-- ══ WHAT'S INSIDE — bento grid ══ --}}
<section id="inside" class="l-section-pad l-section-tint">
  <div class="l-wrap">
    <div class="l-head l-reveal l-stagger">
      <span class="l-eyebrow">What's inside</span>
      <h2 class="l-h2">Built around the way you actually learn.</h2>
      <p class="l-sub">Clear paths, honest feedback, and progress you can see at a glance.</p>
    </div>

    @php
        $showcase = [
            ['tone' => 'teal',  'label' => 'Courses',    'title' => 'Courses built as clear paths',  'body' => 'Every course is organised into modules and lessons — video, reading and practice — so you always know where you are and what comes next.', 'src' => 'images/landing/feature-courses.jpg',    'alt' => 'A course dashboard with the lesson playing, bookmarked moments, and path progress.', 'icon' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>'],
            ['tone' => 'amber', 'label' => 'Assessment', 'title' => 'Test yourself, know for sure',  'body' => 'Quizzes and exams with instant, automatic grading. Your results arrive the moment they are ready, and passing an assessment completes the lesson for you.', 'src' => 'images/landing/feature-assessment.jpg', 'alt' => 'An assessments screen with quizzes, progress bars and recent results.', 'icon' => '<path d="M9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'],
            ['tone' => 'red',   'label' => 'Progress',   'title' => 'See your progress build',       'body' => 'Every lesson you finish rolls up to course completion. Pick up exactly where you left off, and see how far you have come at a glance.', 'src' => 'images/landing/feature-progress.jpg',   'alt' => 'A progress dashboard with completion charts and recent activity.', 'icon' => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>'],
        ];
        $strip = [
            ['title' => 'Protected content',      'body' => 'Streams only to enrolled learners',           'icon' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>'],
            ['title' => 'Notifications that land','body' => 'Confirmations, results and completions',      'icon' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>'],
            ['title' => 'Your data stays yours',  'body' => 'Account, courses and results, private to you','icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
        ];
    @endphp

    {{-- Interactive showcase: pick a feature on the left, the screen on the right changes.
         Works without JavaScript — the first panel is shown and the buttons are plain anchors. --}}
    <div class="l-showcase l-reveal" data-showcase>
      <div class="l-showcase-list" role="tablist" aria-label="Features">
        @foreach ($showcase as $item)
          <button type="button" class="l-showcase-tab l-showcase-tab-{{ $item['tone'] }} l-stagger-item{{ $loop->first ? ' is-active' : '' }}" style="--i:{{ $loop->index }}" role="tab" id="tab-{{ $loop->index }}" aria-controls="panel-{{ $loop->index }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-index="{{ $loop->index }}">
            <span class="l-showcase-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg></span>
            <span class="l-showcase-text">
              <span class="l-eyebrow">{{ $item['label'] }}</span>
              <span class="l-showcase-title">{{ $item['title'] }}</span>
              <span class="l-showcase-body">{{ $item['body'] }}</span>
            </span>
            <span class="l-showcase-progress" aria-hidden="true"></span>
          </button>
        @endforeach
      </div>

      <div class="l-showcase-stage">
        @foreach ($showcase as $item)
          <div class="l-showcase-panel l-showcase-panel-{{ $item['tone'] }}{{ $loop->first ? ' is-active' : '' }}" role="tabpanel" id="panel-{{ $loop->index }}" aria-labelledby="tab-{{ $loop->index }}" @unless ($loop->first) hidden @endunless>
            <div class="l-device">
              <div class="l-device-bar"><span></span><span></span><span></span></div>
              <div class="l-device-screen">
                @include('partials.landing-image-slot', ['src' => $item['src'], 'caption' => $item['title'], 'alt' => $item['alt'], 'fit' => 'cover'])
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    {{-- Three more things, as a plain strip rather than boxes. --}}
    <ul class="l-strip l-reveal">
      @foreach ($strip as $item)
        <li class="l-stagger-item" style="--i:{{ $loop->index }}">
          <span class="l-strip-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg></span>
          <span><strong>{{ $item['title'] }}</strong><span class="l-strip-body">{{ $item['body'] }}</span></span>
        </li>
      @endforeach
    </ul>
  </div>
</section>

{{-- ══ THE IDEA — one line, large ══ --}}
<section class="l-quote">
  <div class="l-motif l-motif-left" aria-hidden="true"></div>
  <div class="l-wrap l-reveal l-stagger">
    <span class="l-eyebrow">The Maieutic method</span>
    <blockquote class="l-quote-text">“I cannot teach anybody anything. I can only make them think.”</blockquote>
    <p class="l-quote-cite">Socrates — and the reason every lesson here ends with a question, not a summary.</p>
  </div>
</section>

{{-- ══ CERTIFICATE ══ --}}
<section id="certificate" class="l-certband">
  <div class="l-blob l-blob-d" aria-hidden="true"></div>
  <div class="l-wrap l-certband-grid l-reveal">
    <div class="l-stagger">
      <span class="l-eyebrow l-eyebrow-light">Certificates</span>
      <h2 class="l-h2 l-h2-light">Finish a course. Earn proof that holds up.</h2>
      <p class="l-sub l-sub-light">Complete the lessons, pass the assessments, and your certificate is issued automatically — with a verification link an employer can check in seconds.</p>
      <ul class="l-ticks l-ticks-light">
        <li><span class="l-tick l-tick-light">{!! $check !!}</span>Issued the moment you complete the course</li>
        <li><span class="l-tick l-tick-light">{!! $check !!}</span>Downloadable PDF, always in your account</li>
        <li><span class="l-tick l-tick-light">{!! $check !!}</span>Verifiable online by anyone you share it with</li>
      </ul>
    </div>
    {{-- The road to a certificate, as an animated timeline. Drawn in CSS,
         no learner, course or date is ever named. Decorative only. --}}
    <div class="l-tl" aria-hidden="true">
      <div class="l-tl-head">
        <span class="l-eyebrow">Your path to the certificate</span>
        <span class="l-tl-status"><span class="l-tl-status-dot"></span>Live progress</span>
      </div>
      <ol class="l-tl-list">
        <li class="l-tl-item is-done" style="--d:0">
          <span class="l-tl-mark">{!! $check !!}</span>
          <span class="l-tl-text"><strong>Lessons completed</strong><span>Every module watched, read and practised</span></span>
          <span class="l-tl-meta">6 / 6</span>
        </li>
        <li class="l-tl-item is-done" style="--d:1">
          <span class="l-tl-mark">{!! $check !!}</span>
          <span class="l-tl-text"><strong>Assessments passed</strong><span>Graded automatically, the moment you submit</span></span>
          <span class="l-tl-meta">Passed</span>
        </li>
        <li class="l-tl-item is-done" style="--d:2">
          <span class="l-tl-mark">{!! $check !!}</span>
          <span class="l-tl-text"><strong>Final test cleared</strong><span>One last check that the learning stuck</span></span>
          <span class="l-tl-meta">92%</span>
        </li>
        <li class="l-tl-item is-issue" style="--d:3">
          <span class="l-tl-mark l-tl-mark-amber"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/></svg></span>
          <span class="l-tl-text"><strong>Certificate issued</strong><span>Automatically, with a verification link</span></span>
          <span class="l-tl-meta l-tl-meta-amber">Just now</span>
        </li>
      </ol>
      <div class="l-tl-foot">
        <span class="l-tl-link"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>verify.maieutic.in/…</span>
        <span class="l-tl-seal"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>Verified</span>
      </div>
    </div>
  </div>
</section>

{{-- ══ FAQ ══ --}}
@php
    $faqs = [
        ['q' => 'Do I have to pay to look at a course?',     'a' => 'No. Every course, its outline and its modules are open to browse. You pay only when you decide to enrol.'],
        ['q' => 'When does my access start?',                'a' => 'As soon as your payment is verified. The enrolment activates automatically — nothing to wait for, no one to chase.'],
        ['q' => 'Can I learn around a full-time job?',       'a' => 'Yes. Lessons are self-paced, work on any device, and your progress is saved as you go, so a ten-minute session still counts.'],
        ['q' => 'How are assessments graded?',               'a' => 'Automatically, the moment you submit. You see your result straight away, and passing an assessment completes its lesson.'],
        ['q' => 'What do I get at the end?',                 'a' => 'A certificate of completion, issued automatically once every lesson and required assessment is done, with a link anyone can use to verify it.'],
        ['q' => 'Is my payment safe?',                       'a' => 'Yes. Access is granted only after the payment is verified on our side — never on the basis of a message from your browser.'],
    ];
@endphp
<section id="faq" class="l-section-pad">
  <div class="l-wrap">
    <div class="l-head l-head-center l-reveal l-stagger">
      <span class="l-eyebrow">FAQ</span>
      <h2 class="l-h2">Questions, answered.</h2>
      <p class="l-sub">The things people ask before their first course.</p>
    </div>
    <div class="l-faq l-reveal">
      @foreach ($faqs as $faq)
        <details class="l-faq-item l-stagger-item" style="--i:{{ $loop->index }}" @if ($loop->first) open @endif>
          <summary><span>{{ $faq['q'] }}</span><span class="l-faq-plus" aria-hidden="true"></span></summary>
          <p>{{ $faq['a'] }}</p>
        </details>
      @endforeach
    </div>
  </div>
</section>

{{-- ══ CLOSING ══ --}}
<section class="l-closing">
  <div class="l-blob l-blob-e" aria-hidden="true"></div>
  <div class="l-motif" aria-hidden="true"></div>
  <div class="l-wrap l-reveal l-stagger l-closing-inner">
    <span class="l-eyebrow l-eyebrow-light">Get started</span>
    <h2 class="l-h2 l-h2-light l-h2-xl">Start learning by asking.</h2>
    <p class="l-sub l-sub-light">Real courses, a clear method, and progress you can see. Browse the catalogue free, and enrol when a course is right for you.</p>
    <div class="l-hero-actions l-center">
      <a href="{{ $catalogueUrl }}" class="l-btn l-btn-amber l-btn-lg">Browse the courses <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
      @guest
        <a href="{{ $registerUrl }}" class="l-btn l-btn-ghost-light l-btn-lg">Create a free account</a>
      @endguest
    </div>
  </div>
</section>

</main>

<footer class="l-footer">
  <div class="l-wrap l-footer-inner">
    <div class="l-footer-brand">
      <img src="{{ asset('images/logo-maieutic-edutech.png') }}" alt="{{ $organisation }}">
      <p>Online courses taught the Socratic way — learn by questioning, not cramming.</p>
    </div>
    <nav class="l-footer-links" aria-label="Footer">
      <a href="{{ $catalogueUrl }}">Courses</a>
      <a href="#how">How it works</a>
      <a href="#inside">What's inside</a>
      <a href="#certificate">Certificates</a>
      <a href="#faq">FAQ</a>
    </nav>
    <div class="l-footer-copy">© {{ now()->year }} {{ $organisation }}. All rights reserved.</div>
  </div>
</footer>

{{-- Reveal-on-scroll. Opt-in: .has-reveal is only added here, so without
     this script the page is fully visible. A safety timer reveals
     everything regardless, so a stalled observer can never leave a section blank. --}}
<script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    var root = document.querySelector('.landing');
    var els = document.querySelectorAll('.l-reveal');
    var header = document.querySelector('.l-header');
    var onScroll = function () { if (header) header.classList.toggle('is-scrolled', window.scrollY > 72); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    if (!root || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    root.classList.add('has-reveal');
    var showAll = function () { els.forEach(function (el) { el.classList.add('is-in'); }); };
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { entry.target.classList.add('is-in'); io.unobserve(entry.target); }
      });
    }, { threshold: 0.12 });
    els.forEach(function (el) { io.observe(el); });
    setTimeout(showAll, 1500);
  })();

  // Feature showcase: tabs switch the framed screenshot. Auto-advances every
  // 6s until the visitor interacts, and never while the pointer is over it.
  (function () {
    var root = document.querySelector('[data-showcase]');
    if (!root) return;
    var tabs = Array.prototype.slice.call(root.querySelectorAll('.l-showcase-tab'));
    var panels = Array.prototype.slice.call(root.querySelectorAll('.l-showcase-panel'));
    var current = 0, timer = null, manual = false;
    var show = function (i) {
      current = i;
      tabs.forEach(function (t, k) { t.classList.toggle('is-active', k === i); t.setAttribute('aria-selected', k === i ? 'true' : 'false'); });
      panels.forEach(function (p, k) { p.classList.toggle('is-active', k === i); p.hidden = k !== i; });
    };
    var next = function () { show((current + 1) % tabs.length); };
    var start = function () { if (timer || manual || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return; timer = setInterval(next, 6000); root.classList.add('is-auto'); };
    var stop = function () { if (timer) { clearInterval(timer); timer = null; } root.classList.remove('is-auto'); };
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { manual = true; stop(); show(i); });
      t.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowRight') { e.preventDefault(); manual = true; stop(); show((i + 1) % tabs.length); tabs[current].focus(); }
        if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') { e.preventDefault(); manual = true; stop(); show((i - 1 + tabs.length) % tabs.length); tabs[current].focus(); }
      });
    });
    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    show(0); start();
  })();
</script>
@endsection
