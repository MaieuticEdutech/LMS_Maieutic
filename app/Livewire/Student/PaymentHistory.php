<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "You used to have this" — the screen MyCourses's own docblock names as
 * Phase 12's job (FR-ENR-*, architecture.md §11).
 *
 * MyCourses shows only what a student can open RIGHT NOW: a revoked or
 * expired enrollment simply disappears from it, deliberately, so a course
 * card never 403s when clicked. That leaves nowhere for "I bought this, what
 * happened to it" — this screen is that place. It lists every order the
 * student has ever placed, successful or not, so a past purchase is never
 * simply gone from view.
 *
 * OWNERSHIP IS THE ONLY FILTER, AND IT IS APPLIED HERE, NOT DELEGATED. Unlike
 * the admin OrdersTable, this component builds its own query scoped to
 * `auth()->id()` rather than accepting any input that could widen it — there
 * is no route parameter here for a student to tamper with. OrderPolicy's own
 * `view()` rule (`isStudent() && is($order->user)`) would independently
 * refuse a stranger's order if this component ever routed to one, but the
 * query never produces one to refuse.
 */
#[Layout('layouts.student')]
#[Title('Payment history')]
final class PaymentHistory extends Component
{
    public function render(): View
    {
        /** @var User $student */
        $student = Auth::user();

        return view('livewire.student.payment-history', [
            'orders' => $this->orders($student),
        ]);
    }

    /**
     * @return Collection<int, Order>
     */
    private function orders(User $student): Collection
    {
        return Order::query()
            ->where('user_id', $student->getKey())
            ->with('course:id,title,slug')
            ->latest('placed_at')
            ->get();
    }
}
