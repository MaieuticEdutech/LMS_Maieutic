{{--
    Marks figures on screen that are placeholders, not real data.

    Required wherever App\Support\DemoMetrics supplies a value. The point is
    that nobody — a stakeholder in a review, a developer, or a learner if this
    ever escaped to production — can mistake an invented number for a measured
    one. Small, but never silent.

    Renders nothing when placeholders are off, so production pages are clean
    without a second condition at every call site.
--}}
@if (\App\Support\DemoMetrics::enabled())
    <span
        {{-- Warm accent + red from the design's own token list, not Tailwind's
             default amber: the theme in app.css replaces the colour namespace,
             so an off-palette utility would silently render unstyled. --}}
        {{ $attributes->merge([
            'class' => 'inline-flex shrink-0 items-center rounded-[99px] bg-[#fef1de] px-2 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-[#a31009] ring-1 ring-inset ring-[#f5cbc9]',
        ]) }}
        title="These figures are placeholders. The data behind them does not exist yet."
    >
        Sample data
    </span>
@endif
