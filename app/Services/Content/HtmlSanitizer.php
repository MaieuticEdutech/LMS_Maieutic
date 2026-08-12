<?php

declare(strict_types=1);

namespace App\Services\Content;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list sanitisation of administrator-authored rich text (NFR-SEC-06).
 *
 * SANITISE ON SAVE, NOT ON RENDER. Storing hostile markup and trusting every
 * read path to escape it is how XSS survives a refactor: one `{!! !!}` added
 * two phases later and the payload fires. What is in the database is already
 * safe.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * DEPENDENCY NOTE — read this before extending the class.
 *
 * This is a hand-written sanitiser built on ext-dom, not a dedicated library
 * such as HTMLPurifier or symfony/html-sanitizer. That is a deliberate
 * constraint, not a preference: no sanitiser package is installed, and
 * composer.json belongs to another track (planning.md §21.2.2), so adding one
 * is a coordinated decision rather than a unilateral one.
 *
 * The approach here is the safe kind of hand-rolled: a STRICT ALLOW-LIST that
 * discards anything not explicitly permitted, rather than a deny-list trying
 * to enumerate every dangerous construct. Everything unknown is dropped.
 *
 * It is nonetheless narrower and less battle-tested than a dedicated library.
 * **Phase 14 (Security Hardening) should decide whether to replace it**, and
 * that decision belongs with the whole team because it adds a dependency.
 * Recorded so the choice is visible rather than inherited by accident.
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Note the threat model: the author is an authenticated Super Admin, so this
 * is not the primary defence against a hostile stranger. It defends against a
 * compromised admin account and against markup pasted in from elsewhere —
 * both realistic, neither exotic.
 */
final class HtmlSanitizer
{
    /**
     * Tags that survive. Everything else is unwrapped or removed.
     *
     * No `<img>`: images belong in `media_files` with a policy check, not
     * inline with an arbitrary src. No `<style>` or `<span>`: styling comes
     * from the design system, and both are common CSS-injection vectors.
     *
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'ul', 'ol', 'li',
        'h2', 'h3', 'h4',
        'blockquote', 'code', 'pre',
        'a',
    ];

    /**
     * Attributes permitted, per tag. Any attribute not listed is stripped —
     * which removes every `on*` event handler without needing to know their
     * names, and every `style` attribute.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title'],
    ];

    /**
     * URL schemes an <a href> may use.
     *
     * `javascript:` and `data:` are the two that turn a link into script
     * execution, so only these three are permitted and everything else —
     * including scheme-relative and unknown schemes — is dropped.
     *
     * @var list<string>
     */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * Elements removed WITH their contents.
     *
     * Distinct from unknown tags, which are unwrapped so their text survives.
     * The text inside a <script> is the attack, so it goes too.
     *
     * @var list<string>
     */
    private const STRIP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'textarea', 'select', 'option',
        'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript',
    ];

    public function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $trimmed = trim($html);

        if ($trimmed === '') {
            return null;
        }

        $document = new DOMDocument;

        // Suppress parse warnings for imperfect markup — we are rebuilding
        // the tree from an allow-list anyway, so malformed input is expected
        // and harmless.
        $previous = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__root__">'.$trimmed.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            // Unparseable. Fall back to plain text rather than storing
            // something we could not inspect.
            return $this->asPlainText($trimmed);
        }

        $root = $document->getElementById('__root__');

        if (! $root instanceof DOMElement) {
            return $this->asPlainText($trimmed);
        }

        $this->clean($root);

        $result = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $result .= $document->saveHTML($child);
        }

        $result = trim($result);

        return $result === '' ? null : $result;
    }

    /**
     * Sanitise to plain text — no markup permitted at all.
     *
     * For fields like a course subtitle where formatting is never wanted.
     */
    public function plainText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($this->asPlainText($value));

        return $clean === '' ? null : $clean;
    }

    /**
     * Depth-first clean. Iterates over a SNAPSHOT of the child list because
     * we mutate the tree while walking it.
     */
    private function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $this->cleanElement($child);

                continue;
            }

            // Comments and processing instructions carry no content the user
            // needs and have historically been parser-confusion vectors.
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $child->parentNode?->removeChild($child);
            }
        }
    }

    private function cleanElement(DOMElement $element): void
    {
        $tag = mb_strtolower($element->tagName);

        if (in_array($tag, self::STRIP_WITH_CONTENT, true)) {
            $element->parentNode?->removeChild($element);

            return;
        }

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            // Unknown but not dangerous: keep the text, drop the tag. A
            // <div> becomes its contents rather than vanishing.
            $this->clean($element);
            $this->unwrap($element);

            return;
        }

        $this->stripAttributes($element, $tag);

        // Recurse only after this element is known to be safe.
        $this->clean($element);
    }

    /**
     * Remove every attribute not explicitly allowed for this tag.
     *
     * Allow-list, so `onclick`, `onerror`, `style`, `srcset` and every future
     * attribute nobody has thought of are all removed without being named.
     */
    private function stripAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        /** @var list<DOMAttr> $attributes */
        $attributes = iterator_to_array($element->attributes ?? []);

        foreach ($attributes as $attribute) {
            $name = mb_strtolower($attribute->name);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if ($name === 'href' && ! $this->isSafeUrl($attribute->value)) {
                $element->removeAttribute($attribute->name);
            }
        }

        // Surviving links open externally and must not hand the opener
        // window to the destination.
        if ($tag === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('rel', 'nofollow noopener noreferrer');
        }
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        // Relative and anchor links are fine and carry no scheme.
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        // Scheme-relative ("//evil.test") inherits the current scheme and is
        // easy to miss when reading. Rejected.
        if (str_starts_with($url, '//')) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            return false;
        }

        return in_array(mb_strtolower($scheme), self::ALLOWED_SCHEMES, true);
    }

    /**
     * Replace an element with its children.
     */
    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }

    private function asPlainText(string $value): string
    {
        return html_entity_decode(
            strip_tags($value),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
    }
}
