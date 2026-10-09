<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CertificationController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CvController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\EducationController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\ExperienceController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\LanguageController;
use App\Http\Controllers\Api\V1\MatchController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\SavedJobController;
use App\Http\Controllers\Api\V1\SkillController;
use App\Http\Controllers\Api\V1\TaxonomyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Versioned API (docs/SPEC.md "API architecture")
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    // Auth routes are rate limited to blunt credential-stuffing/abuse.
    Route::prefix('auth')->middleware('throttle:6,1')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::get('me', [AuthController::class, 'me'])->name('auth.me');
            Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');
            Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:6,1')
                ->name('verification.send');
        });
    });

    /*
    | Phase 2 — Candidate platform (SPEC section 4.1). All routes require an
    | authenticated token; ownership is enforced by policies server-side.
    */
    Route::middleware('auth:sanctum')->group(function (): void {
        // Profile & preferences
        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::match(['put', 'patch'], 'profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('profile/{profile}/photo', [ProfileController::class, 'photo'])->name('profile.photo');
        Route::get('profile/preferences', [PreferenceController::class, 'show'])->name('preferences.show');
        Route::match(['put', 'patch'], 'profile/preferences', [PreferenceController::class, 'update'])->name('preferences.update');

        // Profile sub-entities
        Route::apiResource('profile/education', EducationController::class);
        Route::apiResource('profile/experiences', ExperienceController::class);
        Route::apiResource('profile/skills', SkillController::class)->except('show');
        Route::apiResource('profile/projects', ProjectController::class);
        Route::apiResource('profile/certifications', CertificationController::class);
        Route::apiResource('profile/languages', LanguageController::class)->except('show');

        // CV versions (SPEC sections 13 + 38: private storage, controlled downloads)
        Route::get('cv', [CvController::class, 'index'])->name('cv.index');
        Route::post('cv/upload', [CvController::class, 'upload'])
            ->middleware('throttle:10,1')
            ->name('cv.upload');
        Route::get('cv/{cv}', [CvController::class, 'show'])->name('cv.show');
        Route::post('cv/{cv}/parse', [CvController::class, 'parse'])
            ->middleware('throttle:10,1')
            ->name('cv.parse');
        Route::post('cv/{cv}/confirm', [CvController::class, 'confirm'])
            ->middleware('throttle:10,1')
            ->name('cv.confirm');
        Route::post('cv/{cv}/tailor', [CvController::class, 'tailor'])
            ->middleware('throttle:10,1')
            ->name('cv.tailor');
        Route::get('cv/{cv}/download', [CvController::class, 'download'])->name('cv.download');
        Route::delete('cv/{cv}', [CvController::class, 'destroy'])->name('cv.destroy');

        // Documents
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('documents', [DocumentController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('documents.store');
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

        // Read-only taxonomy lookups for typeaheads/selects
        Route::prefix('taxonomy')->name('taxonomy.')->group(function (): void {
            Route::get('skills', [TaxonomyController::class, 'skills'])->name('skills');
            Route::get('education-levels', [TaxonomyController::class, 'educationLevels'])->name('education-levels');
            Route::get('institutions', [TaxonomyController::class, 'institutions'])->name('institutions');
            Route::get('industries', [TaxonomyController::class, 'industries'])->name('industries');
            Route::get('job-categories', [TaxonomyController::class, 'jobCategories'])->name('job-categories');
            Route::get('job-roles', [TaxonomyController::class, 'jobRoles'])->name('job-roles');
        });

        /*
        | Phase 3 — Jobs (SPEC sections 5, 16-18). Reads are limited to published
        | jobs by JobPolicy; employer posting/management arrives in Phase 9.
        */
        Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
        Route::get('jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
        Route::post('jobs/{job}/save', [SavedJobController::class, 'store'])->name('jobs.save');
        Route::delete('jobs/{job}/save', [SavedJobController::class, 'destroy'])->name('jobs.unsave');
        Route::get('saved-jobs', [SavedJobController::class, 'index'])->name('saved-jobs.index');
        Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');

        /*
        | Phase 4 — Matching (SPEC section 19). Scores are derived from database
        | facts by the deterministic engine and stored on every read; no model
        | decides anything factual here.
        */
        Route::get('matches', [MatchController::class, 'index'])->name('matches.index');
        Route::get('jobs/{job}/match', [MatchController::class, 'show'])->name('jobs.match');
    });
});
