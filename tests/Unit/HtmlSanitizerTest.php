<?php

declare(strict_types=1);

use App\Services\Content\HtmlSanitizer;

/*
|--------------------------------------------------------------------------
| HtmlSanitizer — XSS vectors (NFR-SEC-06)
|--------------------------------------------------------------------------
|
| Sanitisation happens ON SAVE, so what is in the database is already safe.
| These tests are the evidence for that claim.
|
| Pure logic: no container, no database.
|
*/

beforeEach(function (): void {
    $this->sanitizer = new HtmlSanitizer;
});

it('keeps legitimate formatting', function (): void {
    $html = '<p>Hello <strong>world</strong> and <em>others</em>.</p><ul><li>One</li></ul>';

    expect($this->sanitizer->sanitize($html))
        ->toContain('<strong>world</strong>')
        ->toContain('<em>others</em>')
        ->toContain('<li>One</li>');
});

/*
| SCRIPT EXECUTION — the vectors that matter.
*/
it('removes script tags with their contents', function (): void {
    $result = $this->sanitizer->sanitize('<p>Safe</p><script>alert(document.cookie)</script>');

    expect($result)->not->toContain('script')
        // The payload text goes too — it IS the attack.
        ->and($result)->not->toContain('alert')
        ->and($result)->toContain('Safe');
});

it('strips every event handler attribute', function (string $payload): void {
    $result = (string) $this->sanitizer->sanitize($payload);

    expect(strtolower($result))
        ->not->toContain('onerror')
        ->not->toContain('onclick')
        ->not->toContain('onload')
        ->not->toContain('onmouseover')
        ->not->toContain('alert(');
})->with([
    '<img src=x onerror="alert(1)">',
    '<p onclick="alert(1)">text</p>',
    '<body onload="alert(1)">text</body>',
    '<div onmouseover="alert(1)">text</div>',
    '<a href="#" onclick="alert(1)">link</a>',
]);

it('refuses javascript and data URLs in links', function (string $href): void {
    $result = (string) $this->sanitizer->sanitize('<a href="'.$href.'">click</a>');

    expect(strtolower($result))
        ->not->toContain('javascript:')
        ->not->toContain('data:')
        ->not->toContain('vbscript:')
        // The link text survives; only the dangerous href is dropped.
        ->and($result)->toContain('click');
})->with([
    'javascript:alert(1)',
    'JaVaScRiPt:alert(1)',
    'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    'vbscript:msgbox(1)',
    '//evil.example.com',
]);

it('keeps safe link schemes and hardens the link', function (string $href): void {
    $result = (string) $this->sanitizer->sanitize('<a href="'.$href.'">click</a>');

    expect($result)->toContain($href)
        // A surviving link must not hand the opener window to its target.
        ->and($result)->toContain('noopener');
})->with([
    'https://example.com/page',
    'http://example.com',
    'mailto:someone@example.com',
    '/relative/path',
]);

it('removes dangerous embedded elements entirely', function (string $payload, string $forbidden): void {
    expect(strtolower((string) $this->sanitizer->sanitize($payload)))->not->toContain($forbidden);
})->with([
    ['<iframe src="https://evil.test"></iframe>', 'iframe'],
    ['<object data="x.swf"></object>', 'object'],
    ['<embed src="x.swf">', 'embed'],
    ['<style>body{display:none}</style>', 'style'],
    ['<form action="/steal"><input name="p"></form>', 'form'],
    ['<svg onload="alert(1)"></svg>', 'svg'],
    ['<meta http-equiv="refresh" content="0;url=https://evil.test">', 'meta'],
    ['<base href="https://evil.test/">', 'base'],
]);

it('strips style attributes used for CSS injection', function (): void {
    $result = (string) $this->sanitizer->sanitize(
        '<p style="position:fixed;top:0;left:0;width:100vw;height:100vh">overlay</p>',
    );

    expect($result)->not->toContain('style=')->and($result)->toContain('overlay');
});

it('unwraps unknown tags but keeps their text', function (): void {
    // A <div> is not dangerous, just not in the allow-list. Its content is
    // the author's work and must survive.
    $result = (string) $this->sanitizer->sanitize('<div><span>kept text</span></div>');

    expect($result)->toContain('kept text')
        ->and($result)->not->toContain('<div')
        ->and($result)->not->toContain('<span');
});

it('removes html comments', function (): void {
    expect((string) $this->sanitizer->sanitize('<p>visible</p><!-- hidden -->'))
        ->not->toContain('hidden')
        ->toContain('visible');
});

it('survives deeply nested and malformed markup', function (): void {
    // Unbalanced tags are normal input from a paste. The parser must not
    // throw, and the payload must still be removed.
    $result = $this->sanitizer->sanitize('<p><b><i>unclosed<script>alert(1)</script>');

    expect(strtolower((string) $result))->not->toContain('script')->not->toContain('alert');
});

it('returns null for empty input', function (?string $input): void {
    expect($this->sanitizer->sanitize($input))->toBeNull();
})->with([null, '', '   ', "\n\t "]);

/*
| plainText() — for fields where no markup is ever wanted.
*/
it('strips all markup in plain text mode', function (): void {
    expect($this->sanitizer->plainText('<b>Bold</b> and <script>alert(1)</script>'))
        ->toBe('Bold and alert(1)');
});

it('decodes entities in plain text mode', function (): void {
    expect($this->sanitizer->plainText('Tom &amp; Jerry'))->toBe('Tom & Jerry');
});
