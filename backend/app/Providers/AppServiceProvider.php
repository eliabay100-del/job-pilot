<?php

namespace App\Providers;

use App\Models\CandidateCertification;
use App\Models\CandidateEducation;
use App\Models\CandidateExperience;
use App\Models\CandidateLanguage;
use App\Models\CandidateProfile;
use App\Models\CandidateProject;
use App\Models\CandidateSkill;
use App\Models\Company;
use App\Models\CvVersion;
use App\Models\Document;
use App\Models\Job;
use App\Policies\CandidateCertificationPolicy;
use App\Policies\CandidateEducationPolicy;
use App\Policies\CandidateExperiencePolicy;
use App\Policies\CandidateLanguagePolicy;
use App\Policies\CandidateProfilePolicy;
use App\Policies\CandidateProjectPolicy;
use App\Policies\CandidateSkillPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\CvVersionPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\JobPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerRouteConstraints();

        Gate::policy(CandidateProfile::class, CandidateProfilePolicy::class);
        Gate::policy(CandidateEducation::class, CandidateEducationPolicy::class);
        Gate::policy(CandidateExperience::class, CandidateExperiencePolicy::class);
        Gate::policy(CandidateSkill::class, CandidateSkillPolicy::class);
        Gate::policy(CandidateProject::class, CandidateProjectPolicy::class);
        Gate::policy(CandidateCertification::class, CandidateCertificationPolicy::class);
        Gate::policy(CandidateLanguage::class, CandidateLanguagePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(CvVersion::class, CvVersionPolicy::class);
        Gate::policy(Job::class, JobPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
    }

    /**
     * Every model-bound route parameter is a bigint primary key. Without these
     * patterns a non-numeric segment reaches Eloquent and PostgreSQL fails with
     * "invalid input syntax for type bigint" — a 500 instead of a 404.
     */
    private function registerRouteConstraints(): void
    {
        $numeric = [
            'profile',
            'cv',
            'document',
            'education',
            'experience',
            'skill',
            'project',
            'certification',
            'language',
            'job',
        ];

        foreach ($numeric as $parameter) {
            Route::pattern($parameter, '[0-9]+');
        }
    }
}
