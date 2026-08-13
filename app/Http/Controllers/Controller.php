<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller.
 *
 * Carries AuthorizesRequests so `$this->authorize()` is available everywhere
 * without each controller remembering the trait. Development Rule 20 requires
 * a policy check after every fetch-by-ID, and a rule that needs a `use`
 * statement before it can be followed is a rule that gets skipped.
 *
 * Laravel 11+ ships this class empty by design; adding the trait is additive
 * and changes nothing for controllers that do not call authorize().
 */
abstract class Controller
{
    use AuthorizesRequests;
}
