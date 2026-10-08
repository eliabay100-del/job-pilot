<?php

namespace App\Providers;

use App\Models\CandidateCertification;
use App\Models\CandidateEducation;
use App\Models\CandidateExperience;
use App\Models\CandidateLanguage;
use App\Models\CandidateProfile;
use App\Models\CandidateProject;
use App\Models\CandidateSkill;
use App\Models\Document;
use App\Policies\CandidateCertificationPolicy;
use App\Policies\CandidateEducationPolicy;
use App\Policies\CandidateExperiencePolicy;
use App\Policies\CandidateLanguagePolicy;
use App\Policies\CandidateProfilePolicy;
use App\Policies\CandidateProjectPolicy;
use App\Policies\CandidateSkillPolicy;
use App\Policies\DocumentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(CandidateProfile::class, CandidateProfilePolicy::class);
        Gate::policy(CandidateEducation::class, CandidateEducationPolicy::class);
        Gate::policy(CandidateExperience::class, CandidateExperiencePolicy::class);
        Gate::policy(CandidateSkill::class, CandidateSkillPolicy::class);
        Gate::policy(CandidateProject::class, CandidateProjectPolicy::class);
        Gate::policy(CandidateCertification::class, CandidateCertificationPolicy::class);
        Gate::policy(CandidateLanguage::class, CandidateLanguagePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
    }
}
