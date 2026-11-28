<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DocsController extends Controller
{
    /**
     * Show the public product and API documentation.
     */
    public function __invoke(): Response
    {
        return Inertia::render('docs/Index', [
            'apiBase' => url('/api/pos'),
            'homeUrl' => route('home'),
            'dashboardUrl' => route('dashboard'),
            'loginUrl' => route('login'),
            'termsUrl' => route('pages.terms'),
            'privacyUrl' => route('pages.privacy'),
        ]);
    }
}
