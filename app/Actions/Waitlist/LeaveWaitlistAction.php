<?php

declare(strict_types=1);

namespace App\Actions\Waitlist;

use App\Enums\WaitlistStatus;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;

class LeaveWaitlistAction
{
    public function handle(WaitlistEntry $entry, User $actor): WaitlistEntry
    {
        return DB::transaction(function () use ($entry): WaitlistEntry {
            /** @var WaitlistEntry $fresh */
            $fresh = WaitlistEntry::query()
                ->whereKey($entry->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($fresh->status === WaitlistStatus::Waiting) {
                $fresh->status = WaitlistStatus::Left;
                $fresh->save();
            }

            return $fresh;
        }, attempts: 3);
    }
}
