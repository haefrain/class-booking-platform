<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Actions\Waitlist\PromoteNextWaiterAction;
use App\Exceptions\CapacityBelowOccupancyException;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateSessionCapacityAction
{
    public function __construct(
        private readonly PromoteNextWaiterAction $promoteNextWaiter,
    ) {}

    public function handle(ClassSession $session, int $newCapacity, User $actor): ClassSession
    {
        return DB::transaction(function () use ($session, $newCapacity): ClassSession {
            /** @var ClassSession $fresh */
            $fresh = ClassSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($newCapacity < $fresh->booked_count) {
                // Admin must cancel specific bookings explicitly (each one
                // refunds + promotes); the DB CHECK agrees with this rule.
                throw new CapacityBelowOccupancyException;
            }

            $fresh->capacity = $newCapacity;
            $fresh->save();

            // Growing may open seats for waiting members — same lock.
            $this->promoteNextWaiter->withinLockedSession($fresh);

            return $fresh;
        }, attempts: 3);
    }
}
