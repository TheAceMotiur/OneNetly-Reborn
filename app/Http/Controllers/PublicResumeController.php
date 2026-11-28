<?php

namespace App\Http\Controllers;

use App\Models\Resume;
use Inertia\Inertia;
use Inertia\Response;

class PublicResumeController extends Controller
{
    /**
     * Display a shared resume.
     */
    public function __invoke(string $slug): Response
    {
        $resume = Resume::query()
            ->where('slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        return Inertia::render('resumes/Print', [
            'resume' => $resume->payload(),
            'backUrl' => null,
        ]);
    }
}
