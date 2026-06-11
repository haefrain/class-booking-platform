<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Sessions\CancelSessionAction;
use App\Actions\Sessions\UpdateSessionCapacityAction;
use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionOpsController extends Controller
{
    public function cancel(Request $request, ClassSession $session, CancelSessionAction $action): RedirectResponse
    {
        $this->authorize('cancel', $session);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $action->handle($session, $user, (string) $validated['reason']);

        return back()->with('success', 'Session cancelled — attendees were notified.');
    }

    public function updateCapacity(Request $request, ClassSession $session, UpdateSessionCapacityAction $action): RedirectResponse
    {
        $this->authorize('updateCapacity', $session);

        $validated = $request->validate([
            'capacity' => ['required', 'integer', 'between:1,200'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $action->handle($session, (int) $validated['capacity'], $user);

        return back()->with('success', 'Capacity updated.');
    }
}
