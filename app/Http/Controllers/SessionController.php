<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ClassSessionResource;
use App\Models\ClassSession;
use App\Support\SessionViewer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function show(Request $request, ClassSession $session): Response
    {
        $session->load(['classType', 'instructor']);

        return Inertia::render('Sessions/Show', [
            'session' => ClassSessionResource::make($session)->resolve(),
            // Server-computed CTA matrix: the page is a dumb switch.
            'viewer' => SessionViewer::for($request->user(), $session),
        ]);
    }
}
