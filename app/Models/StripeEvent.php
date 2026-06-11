<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Webhook replay ledger: the unique stripe_event_id makes duplicate
 * deliveries a 200-and-done no-op at the controller.
 *
 * @property int $id
 * @property string $stripe_event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property CarbonImmutable|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['stripe_event_id', 'type', 'payload'])]
class StripeEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
