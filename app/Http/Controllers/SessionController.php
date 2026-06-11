<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ClassSessionResource;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function show(Request $request, ClassSession $session): Response
    {
        $session->load(['classType', 'instructor']);

        // Server-computed CTA: the page is a dumb switch. B3 introduces the
        // full matrix (book/pay/join_waitlist/…); until then authenticated
        // viewers see "closed".
        $cta = $request->user() === null ? 'login' : 'closed';

        return Inertia::render('Sessions/Show', [
            'session' => ClassSessionResource::make($session)->resolve(),
            'viewer' => [
                'cta' => $cta,
            ],
        ]);
    }
}
