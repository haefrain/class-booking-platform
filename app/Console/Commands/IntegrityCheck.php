<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\SeatInvariantAuditor;
use Illuminate\Console\Command;

class IntegrityCheck extends Command
{
    protected $signature = 'integrity:check';

    protected $description = 'Audit the seat-integrity invariants (I1–I7) against live data';

    public function handle(): int
    {
        $violations = SeatInvariantAuditor::violations();

        foreach (SeatInvariantAuditor::operationalAlerts() as $alert) {
            $this->warn($alert);
        }

        if ($violations === []) {
            $this->info('All seat invariants hold.');

            return self::SUCCESS;
        }

        foreach ($violations as $violation) {
            $this->error($violation);
        }

        return self::FAILURE;
    }
}
