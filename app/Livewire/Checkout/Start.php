<?php

declare(strict_types=1);

namespace App\Livewire\Checkout;

use App\Actions\Payment\InitiateCheckout;
use App\Enums\CourseStatus;
use App\Models\Course;
use App\Services\Enrollment\EnrollmentAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The checkout form (architecture.md §11.2 steps 1-6).
 *
 * Guest-accessible — see routes/web.php's checkout group docblock for why.
 * A signed-in student has their name and email prefilled and locked (their
 * account email is who the purchase belongs to); a guest fills in both
 * themselves, and {@see \App\Actions\Payment\SettleCapturedPayment} resolves
 * or creates the account those details describe once the payment is
 * verified.
 *
 * DOES NOT GRANT ANYTHING. Submitting this form produces an `Order` and
 * opens Razorpay's own hosted checkout in the browser — see this class's own
 * `checkout:open` event. Nothing this component does can result in an
 * enrollment; that is exclusively the settled-webhook path.
 */
#[Layout('layouts.public')]
final class Start extends Component
{
    public Course $course;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public function mount(Course $course): void
    {
        $this->authorize('view', $course);

        // No zero-price check: courses_price_positive_check (ADR-014) makes
        // that state impossible for any published course — see
        // InitiateCheckout's identical comment.
        if ($course->status !== CourseStatus::Published) {
            abort(404);
        }

        $this->course = $course;

        $user = auth()->user();

        if ($user !== null) {
            if (app(EnrollmentAccessService::class)->grantsAccess($user, $course)) {
                // Already theirs — nothing to sell them a second time.
                $this->redirectRoute('student.courses.play', ['course' => $course], navigate: false);

                return;
            }

            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    public function submit(InitiateCheckout $initiateCheckout): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $order = $initiateCheckout->handle($this->course, [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone !== '' ? $this->phone : null,
            ]);
        } catch (ValidationException $e) {
            $this->addError('course', $e->validator->errors()->first());

            return;
        }

        // Handed to the Alpine component in the view, which opens Razorpay's
        // hosted checkout with these exact values — nothing here trusts
        // anything the browser sends back; settlement happens only from the
        // webhook.
        $this->dispatch('checkout:open', [
            'keyId' => config()->string('lms.payments.razorpay.key_id'),
            'gatewayOrderId' => $order->gateway_order_id,
            'amount' => $order->amount_total,
            'currency' => $order->currency,
            'name' => $this->name,
            'email' => $this->email,
            'contact' => $order->buyer_phone,
            'courseTitle' => $this->course->title,
            'statusUrl' => route('checkout.status', ['order' => $order->order_number]),
        ]);
    }

    public function render(): View
    {
        return view('livewire.checkout.start');
    }
}
