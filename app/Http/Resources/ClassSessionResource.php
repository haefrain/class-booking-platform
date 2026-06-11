<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wire shape for catalog cards and the session detail page. Times travel as
 * ISO-8601 UTC; the browser renders them in the viewer's timezone
 * (resources/js/lib/date.ts).
 *
 * @mixin ClassSession
 */
class ClassSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ClassSession $session */
        $session = $this->resource;
        /** @var ClassType|null $type */
        $type = $session->relationLoaded('classType') ? $session->classType : null;
        /** @var User|null $instructor */
        $instructor = $session->relationLoaded('instructor') ? $session->instructor : null;

        return [
            'id' => $session->id,
            'starts_at' => $session->starts_at->toIso8601ZuluString(),
            'ends_at' => $session->ends_at->toIso8601ZuluString(),
            'capacity' => $session->capacity,
            'spots_left' => $session->spotsLeft(),
            'status' => $session->status->value,
            'class_type' => $type === null ? null : [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'duration_minutes' => $type->duration_minutes,
                'price_cents' => $type->price_cents,
                'is_free' => $type->isFree(),
            ],
            'instructor' => $instructor === null ? null : [
                'id' => $instructor->id,
                'name' => $instructor->name,
            ],
        ];
    }
}
