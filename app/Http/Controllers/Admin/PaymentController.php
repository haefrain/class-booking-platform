<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\RefundPaymentJob;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        $payments = Payment::query()
            ->with(['user', 'booking.session.classType'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'user' => $payment->user?->name,
                'class' => $payment->booking?->session?->classType?->name,
                'amount_cents' => $payment->amount_cents,
                'amount_refunded_cents' => $payment->amount_refunded_cents,
                'currency' => $payment->currency,
                'status' => $payment->status->value,
                'flag' => $payment->flag,
                'refund_requested_at' => $payment->refund_requested_at?->toIso8601String(),
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Payments/Index', ['payments' => $payments]);
    }

    public function retryRefund(Request $request, Payment $payment): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        // Re-arm the claim, then go through the exact same job: one refund
        // path, no special cases.
        $rearmed = DB::table('payments')
            ->where('id', $payment->id)
            ->where('status', PaymentStatus::RefundFailed->value)
            ->update(['status' => PaymentStatus::Succeeded->value]);

        if ($rearmed === 1) {
            RefundPaymentJob::dispatch($payment->id)->onQueue('critical');

            return back()->with('success', 'Refund retry dispatched.');
        }

        return back()->with('error', 'This payment is not in a retryable state.');
    }
}
