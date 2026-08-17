@props(['caption' => 'Image', 'src' => null, 'alt' => null])

{{--
    An image on the landing page, or an honest placeholder where the artwork
    does not exist yet.

    ═════════════════════════════════════════════════════════════════════════
    THE FILE IS CHECKED BEFORE IT IS RENDERED.

    `$src` names a path under public/. If the file is actually there, a real
    <img> is rendered. If it is not, the placeholder appears instead — never a
    broken-image icon, and never an <img> pointing at a 404.

    That check is what makes dropping artwork in a one-step job: the markup
    already references the final path, so adding the file is the whole change.
    It also means a missing file degrades to "not filled in yet" rather than to
    something that looks broken, which matters on the one page every visitor
    sees first.
    ═════════════════════════════════════════════════════════════════════════

    Fills its parent, which owns the height the design specifies — 520px in the
    hero, 400px in the feature rows.
--}}
@php
    $file = $src !== null && $src !== '' && file_exists(public_path($src));
@endphp

@if ($file)
    <img src="{{ asset($src) }}" alt="{{ $alt ?? $caption }}"
         style="height:100%;width:100%;object-fit:cover;display:block">
@else
    <div style="height:100%;width:100%;display:flex;align-items:center;justify-content:center;background:var(--surface-sunken, #f2f1ec);border:1px solid var(--border)">
        <div style="padding:0 24px;text-align:center">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                 stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-subtle);margin:0 auto" aria-hidden="true">
                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                <circle cx="9" cy="9" r="2"></circle>
                <path d="m21 15-3.35-3.35a2 2 0 0 0-2.83 0L6 21"></path>
            </svg>
            <div style="margin-top:12px;font-family:var(--font-mono);font-size:var(--fs-eyebrow);letter-spacing:var(--ls-eyebrow);text-transform:uppercase;color:var(--text-muted)">Image</div>
            <div style="margin-top:4px;max-width:28ch;font-size:var(--fs-sm);color:var(--text-muted)">{{ $caption }}</div>
            @if ($src)
                <div style="margin-top:8px;font-family:var(--font-mono);font-size:11px;color:var(--text-subtle)">{{ $src }}</div>
            @endif
        </div>
    </div>
@endif
