<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| A modal opened from x-init must defer by a tick
|--------------------------------------------------------------------------
|
| ═════════════════════════════════════════════════════════════════════════
| THIS BUG SHIPPED THREE TIMES: Add module, Add lesson, Add question.
|
| Alpine initialises a parent before its children. An `x-init` on a wrapper
| therefore runs BEFORE the <x-modal> inside it has registered its
| `x-on:open-modal.window` listener, so dispatching immediately sends the
| event into the void. The component state flips, the markup enters the DOM,
| and the dialog never appears.
|
| Nothing errors. Nothing logs. There is no failing request to find, because
| nothing failed — the listener simply did not exist yet. It is invisible to
| every other kind of test, and it was reported by a person three separate
| times.
|
| WHY THIS IS ASSERTED AGAINST SOURCE RATHER THAN BEHAVIOUR: Alpine is
| JavaScript and Pest renders Blade without a browser. There is no DOM, no
| Alpine, and therefore no way to observe the dialog failing to open. Reading
| the markup is the only check available here — and a check that only exists
| in a browser nobody automates is a check that does not exist.
| ═════════════════════════════════════════════════════════════════════════
|
*/

it('defers every x-init modal dispatch by a tick', function (): void {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        foreach (file($file->getPathname()) ?: [] as $number => $line) {
            // An x-init that opens a modal on the same line it initialises.
            if (! str_contains($line, 'x-init') || ! str_contains($line, 'open-modal')) {
                continue;
            }

            if (str_contains($line, 'nextTick')) {
                continue;
            }

            $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).':'.($number + 1);
        }
    }

    expect($offenders)->toBe([],
        "These dispatch 'open-modal' from x-init without \$nextTick, so the modal's own listener "
        ."is not registered yet and the dialog will never open:\n  ".implode("\n  ", $offenders)
        ."\n\nWrap it: x-init=\"\$nextTick(() => \$dispatch('open-modal', 'name'))\"",
    );
});
