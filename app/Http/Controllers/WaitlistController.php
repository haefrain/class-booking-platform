<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Waitlist\JoinWaitlistAction;
use App\Actions\Waitlist\LeaveWaitlistAction;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function store(Request $request, ClassSession $session, JoinWaitlistAction $action): RedirectResponse
    {
        $this->authorize('create', [WaitlistEntry::class, $session]);

        /** @var User $user */
        $user = $request->user();

        $action->handle($user, $session->id);

        return back()->with('success', "You're on the waitlist — we'll email you if a spot opens.");
    }

    public function destroy(Request $request, WaitlistEntry $entry, LeaveWaitlistAction $action): RedirectResponse
    {
        $this->authorize('delete', $entry);

        /** @var User $user */
        $user = $request->user();

        $action->handle($entry, $user);

        return back()->with('success', 'You left the waitlist.');
    }
}
