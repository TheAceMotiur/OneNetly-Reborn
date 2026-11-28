<?php

use App\Http\Controllers\PublicResumeController;
use App\Http\Controllers\ResumeController;
use App\Http\Middleware\PreventIndexing;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('resumes', [ResumeController::class, 'index'])->name('resumes.index');
    Route::get('resumes/templates', [ResumeController::class, 'templates'])->name('resumes.templates');
    Route::post('resumes', [ResumeController::class, 'store'])->name('resumes.store');

    Route::get('resumes/{resume}', [ResumeController::class, 'edit'])
        ->whereNumber('resume')
        ->name('resumes.edit');
    Route::patch('resumes/{resume}', [ResumeController::class, 'update'])
        ->whereNumber('resume')
        ->name('resumes.update');
    Route::delete('resumes/{resume}', [ResumeController::class, 'destroy'])
        ->whereNumber('resume')
        ->name('resumes.destroy');
    Route::post('resumes/{resume}/duplicate', [ResumeController::class, 'duplicate'])
        ->whereNumber('resume')
        ->name('resumes.duplicate');
    Route::post('resumes/{resume}/publish', [ResumeController::class, 'publish'])
        ->whereNumber('resume')
        ->name('resumes.publish');
    Route::get('resumes/{resume}/print', [ResumeController::class, 'printView'])
        ->whereNumber('resume')
        ->middleware(PreventIndexing::class)
        ->name('resumes.print');
});

Route::get('r/{slug}', PublicResumeController::class)
    ->middleware(PreventIndexing::class)
    ->name('resume.public');
