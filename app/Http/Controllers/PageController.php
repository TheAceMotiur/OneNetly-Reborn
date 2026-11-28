<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    /** Show the Terms of Service page. */
    public function terms(): Response
    {
        return Inertia::render('terms/Index', [
            'title' => 'Terms of Service',
        ]);
    }

    /** Show the Privacy Policy page. */
    public function privacy(): Response
    {
        return Inertia::render('privacy/Index', [
            'title' => 'Privacy Policy',
        ]);
    }
}
