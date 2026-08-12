<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administrator Area — SUPER ADMIN ONLY
|--------------------------------------------------------------------------
|
| Every route in this file MUST sit behind, in this order:
|     ->middleware(['auth', 'active', 'role:super_admin'])
|
| Middleware is the coarse gate only. It is NOT sufficient on its own: each
| controller/Livewire action must additionally authorise the specific record
| through its Policy (FR-RBAC-02, FR-RBAC-03, architecture.md §8.2). Middleware
| answers "may this kind of user be here?"; the policy answers "may THIS user
| touch THIS record?".
|
| Delivered by:
|   Phase 4  — dashboard, student & instructor management, instructor↔course
|              assignment, settings, audit log viewer
|   Phase 5  — course CRUD, Course Builder, content upload
|   Phase 6  — enrollment grant/revoke, enrollment listing
|   Phase 8  — assessment & question management
|   Phase 12 — orders, payments, webhook event log
|   Phase 13 — reports and exports
|
| The `active` and `role` middleware aliases are registered in Phase 2. This
| file is intentionally empty until then: registering routes behind middleware
| that does not yet exist would fail at boot.
|
*/

Route::prefix('admin')
    ->name('admin.')
    ->group(static function (): void {
        // Phase 4 onwards.
    });
