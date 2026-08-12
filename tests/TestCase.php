<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Disable Vite for the entire Feature suite.
         *
         * WHY THIS EXISTS:
         * @vite() throws when public/build/manifest.json is missing, so
         * without this EVERY test that renders a view returns 500 unless
         * someone has run `npm run build` first.
         *
         * That coupling was wrong twice over: these tests assert routing,
         * authorisation and domain behaviour, none of which depend on
         * compiled CSS — and it made the suite pass locally (where a build
         * existed) while failing in CI (where asset building is a separate
         * job with its own workspace).
         *
         * Found by the first CI run, not by local testing, which is precisely
         * the class of bug CI exists to catch.
         *
         * Tests that genuinely need real asset output belong in
         * tests/Browser (Phase 15), not here.
         */
        $this->withoutVite();
    }
}
